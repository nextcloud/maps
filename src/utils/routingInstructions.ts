/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Turns an OSRM/Mapbox-Directions-API route response (both providers share
 * the same maneuver vocabulary) into a flat, localized list of turn-by-turn
 * instructions, without pulling in osrm-text-instructions or
 * leaflet-routing-machine. Ported from leaflet-routing-machine's
 * OSRMv1._convertRoute()/Formatter.formatInstruction(), which this app used
 * to rely on for the same purpose.
 */

import { translate as t } from '@nextcloud/l10n'

export interface RawManeuver {
	type: string
	modifier?: string
	exit?: number
	bearing_after?: number
	location?: [number, number]
}

export interface RawStep {
	maneuver: RawManeuver
	name: string
	distance: number
	duration: number
}

export interface RawLeg {
	steps: RawStep[]
}

export interface RawRoute {
	legs: RawLeg[]
}

export interface Instruction {
	type: string
	modifier?: string
	direction?: string
	exit?: number
	road: string
	distance: number
	time: number
	location?: [number, number]
}

const DIRECTIONS: Record<string, string> = {
	N: t('maps', 'north'),
	NE: t('maps', 'northeast'),
	E: t('maps', 'east'),
	SE: t('maps', 'southeast'),
	S: t('maps', 'south'),
	SW: t('maps', 'southwest'),
	W: t('maps', 'west'),
	NW: t('maps', 'northwest'),
	SlightRight: t('maps', 'slight right'),
	Right: t('maps', 'right'),
	SharpRight: t('maps', 'sharp right'),
	SlightLeft: t('maps', 'slight left'),
	Left: t('maps', 'left'),
	SharpLeft: t('maps', 'sharp left'),
	Uturn: t('maps', 'Turn around'),
}

const INSTRUCTION_TEMPLATES: Record<string, string[]> = {
	Head: [t('maps', 'Head {dir}'), t('maps', ' on {road}')],
	Continue: [t('maps', 'Continue {dir}')],
	TurnAround: [t('maps', 'Turn around')],
	WaypointReached: [t('maps', 'Waypoint reached')],
	Roundabout: [t('maps', 'Take the {exitStr} exit in the roundabout'), t('maps', ' onto {road}')],
	DestinationReached: [t('maps', 'Destination reached')],
	Fork: [t('maps', 'At the fork, turn {modifier}'), t('maps', ' onto {road}')],
	Merge: [t('maps', 'Merge {modifier}'), t('maps', ' onto {road}')],
	OnRamp: [t('maps', 'Turn {modifier} on the ramp'), t('maps', ' onto {road}')],
	OffRamp: [t('maps', 'Take the ramp on the {modifier}'), t('maps', ' onto {road}')],
	EndOfRoad: [t('maps', 'Turn {modifier} at the end of the road'), t('maps', ' onto {road}')],
	Onto: [t('maps', 'onto {road}')],
}

/**
 * @param s a space-separated maneuver type or modifier, e.g. "on ramp"
 */
function camelCase(s: string): string {
	return s.split(' ').map((w) => w.charAt(0).toUpperCase() + w.substring(1)).join('')
}

/**
 * @param modifier a maneuver modifier, e.g. "slight left"
 */
function leftOrRight(modifier: string): 'Left' | 'Right' {
	return modifier.includes('left') ? 'Left' : 'Right'
}

/**
 * @param bearing degrees, 0-360
 */
function bearingToDirection(bearing = 0): string {
	const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW']
	return directions[Math.round(bearing / 45) % 8]
}

/**
 * @param maneuver the raw OSRM/Mapbox maneuver object
 * @param isLastLeg whether this step belongs to the route's last leg
 */
function maneuverToInstructionType(maneuver: RawManeuver, isLastLeg: boolean): string | undefined {
	switch (maneuver.type) {
	case 'new name':
		return 'Continue'
	case 'depart':
		return 'Head'
	case 'arrive':
		return isLastLeg ? 'DestinationReached' : 'WaypointReached'
	case 'roundabout':
	case 'rotary':
		return 'Roundabout'
	case 'merge':
	case 'fork':
	case 'on ramp':
	case 'off ramp':
	case 'end of road':
		return camelCase(maneuver.type)
	default:
		return maneuver.modifier ? camelCase(maneuver.modifier) : undefined
	}
}

/**
 * @param maneuver the raw OSRM/Mapbox maneuver object
 */
function maneuverToModifier(maneuver: RawManeuver): string | undefined {
	let modifier = maneuver.modifier
	switch (maneuver.type) {
	case 'merge':
	case 'fork':
	case 'on ramp':
	case 'off ramp':
	case 'end of road':
		modifier = modifier ? leftOrRight(modifier) : modifier
	}
	return modifier && camelCase(modifier)
}

/**
 * @param route the raw route from the directions response, requested with `steps=true`
 */
export function buildInstructions(route: RawRoute): Instruction[] {
	const legCount = route.legs.length
	const instructions: Instruction[] = []
	route.legs.forEach((leg, legIndex) => {
		leg.steps.forEach((step) => {
			const type = maneuverToInstructionType(step.maneuver, legIndex === legCount - 1)
			if (!type) {
				return
			}
			instructions.push({
				type,
				modifier: maneuverToModifier(step.maneuver),
				direction: bearingToDirection(step.maneuver.bearing_after),
				exit: step.maneuver.exit,
				road: step.name,
				distance: step.distance,
				time: step.duration,
				location: step.maneuver.location,
			})
		})
	})
	return instructions
}

/**
 * @param str template string with `{placeholder}` tokens
 * @param data values to substitute in
 */
function fillTemplate(str: string, data: Record<string, string | number | undefined>): string {
	return str.replace(/\{(\w+)\}/g, (_, key) => String(data[key] ?? ''))
}

/**
 * @param instr the instruction to render
 * @param i the instruction's index within the flattened instruction list
 */
function instructionTemplate(instr: Instruction, i: number): string {
	const type = instr.type === 'Straight' ? (i === 0 ? 'Head' : 'Continue') : instr.type
	let strings = INSTRUCTION_TEMPLATES[type]
	if (!strings) {
		strings = [DIRECTIONS[type] ?? '', ' ' + INSTRUCTION_TEMPLATES.Onto[0]]
	}
	return strings[0] + (strings.length > 1 && instr.road ? strings[1] : '')
}

/**
 * @param instr the instruction to render
 * @param i the instruction's index within the flattened instruction list
 */
export function formatInstructionText(instr: Instruction, i: number): string {
	const filled = fillTemplate(instructionTemplate(instr, i), {
		...instr,
		exitStr: instr.exit ? `${instr.exit}º` : '',
		dir: instr.direction ? DIRECTIONS[instr.direction] : '',
		modifier: instr.modifier ? DIRECTIONS[instr.modifier] : '',
	})
	return filled.charAt(0).toUpperCase() + filled.substring(1)
}

const ICONS_BY_TYPE: Record<string, string> = {
	WaypointReached: '◆',
	Roundabout: '↻',
	DestinationReached: '🏁',
}

const ICONS_BY_MODIFIER: Record<string, string> = {
	Straight: '↑',
	SlightRight: '↗',
	Right: '➜',
	SharpRight: '↘',
	TurnAround: '↩',
	Uturn: '↩',
	SharpLeft: '↙',
	Left: '⬅',
	SlightLeft: '↖',
}

/**
 * @param instr the instruction to pick an icon for
 * @param i the instruction's index within the flattened instruction list
 */
export function instructionIcon(instr: Instruction, i: number): string {
	if (instr.type === 'Head' && i === 0) {
		return '●'
	}
	return ICONS_BY_TYPE[instr.type] ?? (instr.modifier && ICONS_BY_MODIFIER[instr.modifier]) ?? '↑'
}
