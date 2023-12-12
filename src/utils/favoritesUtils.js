/**
 * @copyright Copyright (c) 2019, Paul Schwörer <hello@paulschwoerer.de>
 *
 * @author Paul Schwörer <hello@paulschwoerer.de>
 * @author Nextcloud Maps contributors
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { hslToRgb, getLetterColor } from './colorUtils.js'

export const getDefaultCategoryName = () => {
	return t('maps', 'Personal')
}

export const getCategoryKey = categoryName =>
	categoryName.replace(' ', '-').toLowerCase()

export const getThemingColorFromCategoryKey = categoryName => {
	let color = '0000EE'

	if (categoryName.length > 1) {
		const hsl = getLetterColor(categoryName[0], categoryName[1])
		color = hslToRgb(hsl.h / 360, hsl.s / 100, hsl.l / 100)
	}

	if (categoryName === getCategoryKey(getDefaultCategoryName())) {
		color = (OCA.Theming ? OCA.Theming.color : '#0082c9').replace('#', '')
	}

	return `#${color}`
}
