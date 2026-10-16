<template>
	<div class="photo-suggestions">
		<div v-if="loading && suggestions.length === 0" class="photo-suggestions__loading">
			<NcLoadingIcon :size="44" />
		</div>

		<NcEmptyContent v-else-if="suggestions.length === 0"
			:name="t('maps', 'No suggestions found')"
			:description="t('maps', 'Suggestions are based on the tracks and devices that recorded where you were when you took your photos. Upload the GPX tracks of your trips, or track your phone with PhoneTrack, to get suggestions.')">
			<template #icon>
				<IconImageMarker />
			</template>
		</NcEmptyContent>

		<template v-else>
			<section class="photo-suggestions__section">
				<div class="photo-suggestions__selection-header">
					<p class="photo-suggestions__summary">
						{{ n('maps', '{selected} of %n suggestion selected', '{selected} of %n suggestions selected', suggestions.length, { selected: selected.length }) }}
					</p>
					<NcButton variant="tertiary"
						:aria-label="layout === 'list' ? t('maps', 'Show as grid') : t('maps', 'Show as list')"
						:title="layout === 'list' ? t('maps', 'Show as grid') : t('maps', 'Show as list')"
						@click="layout = layout === 'list' ? 'grid' : 'list'">
						<template #icon>
							<IconViewGrid v-if="layout === 'list'" :size="20" />
							<IconViewList v-else :size="20" />
						</template>
					</NcButton>
				</div>
				<div class="photo-suggestions__selection-actions">
					<NcButton variant="secondary" @click="emit('select-all')">
						{{ t('maps', 'Select all') }}
					</NcButton>
					<NcButton variant="tertiary"
						:disabled="selected.length === 0"
						@click="emit('clear-selection')">
						{{ t('maps', 'Clear selection') }}
					</NcButton>
				</div>

				<p v-if="selected.length === 0" class="photo-suggestions__hint">
					{{ t('maps', 'Click the suggested photos on the map to select them.') }}
				</p>

				<ul v-else-if="layout === 'list'" class="photo-suggestions__list">
					<NcListItem v-for="photo in selected"
						:key="photo.index"
						:name="photo.basename"
						:bold="false"
						:details="formatDate(photo)"
						@click="openInViewer(photo)">
						<template #icon>
							<img class="photo-suggestions__thumbnail"
								:src="previewUrl(photo)"
								alt=""
								width="44"
								height="44">
						</template>
						<template #subname>
							{{ photo.path }}
						</template>
						<template #actions>
							<NcActionButton v-for="action in photoActions"
								:key="action.id"
								:close-after-click="true"
								@click="action.run(photo)">
								<template #icon>
									<component :is="action.icon" :size="20" />
								</template>
								{{ action.label }}
							</NcActionButton>
						</template>
					</NcListItem>
				</ul>

				<ul v-else class="photo-suggestions__grid">
					<li v-for="photo in selected"
						:key="photo.index"
						class="photo-suggestions__tile">
						<button class="photo-suggestions__tile-preview"
							:style="{ backgroundImage: `url(${previewUrl(photo)})` }"
							:aria-label="t('maps', 'Display picture {name}', { name: photo.basename })"
							@click="openInViewer(photo)" />
						<NcActions class="photo-suggestions__tile-actions" variant="tertiary-on-primary">
							<NcActionButton v-for="action in photoActions"
								:key="action.id"
								:close-after-click="true"
								@click="action.run(photo)">
								<template #icon>
									<component :is="action.icon" :size="20" />
								</template>
								{{ action.label }}
							</NcActionButton>
						</NcActions>
					</li>
				</ul>
			</section>

			<section class="photo-suggestions__section">
				<h3>{{ t('maps', 'Location sources') }}</h3>
				<ul class="photo-suggestions__sources">
					<li v-for="track in tracks" :key="track.key">
						<NcCheckboxRadioSwitch :model-value="track.enabled"
							:indeterminate="track.indeterminate"
							@update:model-value="toggleTrack(track)">
							<span class="photo-suggestions__source">
								<span class="photo-suggestions__source-color" :style="{ backgroundColor: track.color }" />
								<IconRoad :size="16" />
								<span class="photo-suggestions__source-name">{{ track.name }}</span>
								<NcCounterBubble :count="track.suggestionCount" />
							</span>
						</NcCheckboxRadioSwitch>
						<ul v-if="track.segments.length > 1" class="photo-suggestions__segments">
							<li v-for="(segment, i) in track.segments" :key="segment.key">
								<NcCheckboxRadioSwitch :model-value="segment.enabled"
									@update:model-value="emit('toggle-track-or-device', segment)">
									<span class="photo-suggestions__source">
										<span class="photo-suggestions__source-name">
											{{ t('maps', 'Segment {number}', { number: i + 1 }) }}
										</span>
										<NcCounterBubble :count="segment.suggestionCount" />
									</span>
								</NcCheckboxRadioSwitch>
							</li>
						</ul>
					</li>
					<li v-for="device in devices" :key="device.key">
						<NcCheckboxRadioSwitch :model-value="device.enabled"
							@update:model-value="emit('toggle-track-or-device', device)">
							<span class="photo-suggestions__source">
								<span class="photo-suggestions__source-color" :style="{ backgroundColor: device.color }" />
								<IconCellphone :size="16" />
								<span class="photo-suggestions__source-name">{{ device.name }}</span>
								<NcCounterBubble :count="device.suggestionCount" />
							</span>
						</NcCheckboxRadioSwitch>
					</li>
				</ul>
			</section>
		</template>

		<section class="photo-suggestions__section">
			<h3>{{ t('maps', 'Settings') }}</h3>
			<label class="photo-suggestions__label" :for="`vs-${timezoneUid}__combobox`">
				{{ t('maps', 'Time zone of the photos') }}
			</label>
			<NcTimezonePicker :uid="timezoneUid"
				:model-value="photoSuggestionsTimezone"
				@update:model-value="emit('change-timezone', $event)" />
			<NcCheckboxRadioSwitch type="switch"
				:model-value="!photoSuggestionsHidePhotos"
				@update:model-value="emit('toggle-hide-photos')">
				{{ t('maps', 'Show photos that already have a location') }}
			</NcCheckboxRadioSwitch>
		</section>

		<div class="photo-suggestions__footer">
			<NcButton variant="tertiary" @click="emit('cancel')">
				{{ hasSavedSuggestions ? t('maps', 'Quit') : t('maps', 'Cancel') }}
			</NcButton>
			<NcButton variant="secondary"
				:disabled="loading"
				@click="emit('load-more')">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('maps', 'Load more') }}
			</NcButton>
			<NcButton v-if="suggestions.length > 0"
				variant="primary"
				:disabled="selected.length === 0 || readOnly"
				@click="emit('save')">
				<template #icon>
					<IconCheck :size="20" />
				</template>
				{{ n('maps', 'Save %n location', 'Save %n locations', selected.length) }}
			</NcButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import moment from '@nextcloud/moment'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import {
	NcActionButton,
	NcActions,
	NcButton,
	NcCheckboxRadioSwitch,
	NcCounterBubble,
	NcEmptyContent,
	NcListItem,
	NcLoadingIcon,
	NcTimezonePicker,
} from '@nextcloud/vue'
import { computed, ref } from 'vue'
import IconCellphone from 'vue-material-design-icons/Cellphone.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconEye from 'vue-material-design-icons/Eye.vue'
import IconImageMarker from 'vue-material-design-icons/ImageMarker.vue'
import IconMagnifyPlus from 'vue-material-design-icons/MagnifyPlus.vue'
import IconMapMarkerCheck from 'vue-material-design-icons/MapMarkerCheck.vue'
import IconRoad from 'vue-material-design-icons/RoadVariant.vue'
import IconViewGrid from 'vue-material-design-icons/ViewGrid.vue'
import IconViewList from 'vue-material-design-icons/ViewList.vue'
import { getToken } from '../../utils/common.js'

export interface PhotoSuggestion {
	fileId: number
	path: string
	basename: string
	dateTaken: number
	hasPreview: boolean
	isUpdateable: boolean
	trackOrDeviceId: string
}

/**
 * A track or device that suggested locations. Tracks have one entry per segment,
 * keyed "track:<id>:<segment>", devices one keyed "device:<id>".
 */
export interface LocationSource {
	key: string
	enabled: boolean
	visible: boolean
	color?: string
	name?: string
	id?: number
	suggestionCount?: number
}

type SelectedPhoto = PhotoSuggestion & { index: number }

const props = defineProps<{
	photoSuggestions: Array<PhotoSuggestion | null>
	photoSuggestionsTracksAndDevices: Record<string, LocationSource>
	photoSuggestionsSelectedIndices: number[]
	photoSuggestionsHidePhotos?: boolean
	photoSuggestionsTimezone: string
	loading: boolean
}>()

const emit = defineEmits<{
	'select-all': []
	'clear-selection': [indices?: number[]]
	save: [indices?: number[]]
	zoom: [photo: PhotoSuggestion]
	cancel: []
	'load-more': []
	'change-timezone': [timezone: string]
	'toggle-hide-photos': []
	'toggle-track-or-device': [source: LocationSource]
}>()

const timezoneUid = 'maps-photo-suggestions-timezone'
const layout = ref<'list' | 'grid'>('list')

/** Saved suggestions are replaced by null so the indices of the others stay valid. */
const suggestions = computed(() => props.photoSuggestions.filter((p): p is PhotoSuggestion => p !== null))
const hasSavedSuggestions = computed(() => props.photoSuggestions.includes(null))
const readOnly = computed(() => !suggestions.value.some((p) => p.isUpdateable))

const selected = computed<SelectedPhoto[]>(() => props.photoSuggestionsSelectedIndices
	.map((index) => ({ photo: props.photoSuggestions[index], index }))
	.filter((entry): entry is { photo: PhotoSuggestion, index: number } => !!entry.photo)
	.map(({ photo, index }) => ({ ...photo, index })))

const sources = computed(() => Object.values(props.photoSuggestionsTracksAndDevices).filter((s) => s.visible))

const tracks = computed(() => {
	const byId = new Map<number, LocationSource[]>()
	for (const source of sources.value) {
		if (source.key.startsWith('track:') && source.id !== undefined) {
			byId.set(source.id, [...(byId.get(source.id) ?? []), source])
		}
	}
	return [...byId.values()].map((segments) => {
		const enabledCount = segments.filter((s) => s.enabled).length
		return {
			key: 'track:' + segments[0].id,
			name: segments[0].name,
			color: segments[0].color,
			segments,
			suggestionCount: segments.reduce((sum, s) => sum + (s.suggestionCount ?? 0), 0),
			enabled: enabledCount > 0,
			indeterminate: enabledCount > 0 && enabledCount < segments.length,
		}
	})
})

const devices = computed(() => sources.value.filter((s) => s.key.startsWith('device:')))

/**
 * Enable all segments of a track, or disable all of them when any is enabled.
 *
 * @param track the track to toggle
 */
function toggleTrack(track: { enabled: boolean, segments: LocationSource[] }): void {
	track.segments
		.filter((segment) => segment.enabled === track.enabled)
		.forEach((segment) => emit('toggle-track-or-device', segment))
}

const photoActions = [
	{ id: 'open', label: t('maps', 'Display picture'), icon: IconEye, run: (photo: SelectedPhoto) => openInViewer(photo) },
	{ id: 'save', label: t('maps', 'Save location'), icon: IconMapMarkerCheck, run: (photo: SelectedPhoto) => emit('save', [photo.index]) },
	{ id: 'zoom', label: t('maps', 'Zoom'), icon: IconMagnifyPlus, run: (photo: SelectedPhoto) => emit('zoom', photo) },
	{ id: 'remove', label: t('maps', 'Remove from selection'), icon: IconClose, run: (photo: SelectedPhoto) => emit('clear-selection', [photo.index]) },
]

/**
 * @param photo the suggested photo
 */
function previewUrl(photo: PhotoSuggestion): string {
	if (!photo.hasPreview) {
		return generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
	}
	const token = getToken()
	return token
		? generateUrl('apps/files_sharing/publicpreview/') + token + '?file=' + encodeURIComponent(photo.path) + '&x=256&y=256&a=1'
		: generateUrl('core') + '/preview?fileId=' + photo.fileId + '&x=256&y=256&a=1'
}

/**
 * @param photo the suggested photo
 */
function formatDate(photo: PhotoSuggestion): string {
	return moment.unix(photo.dateTaken).format('L')
}

/**
 * @param photo the suggested photo
 */
function openInViewer(photo: PhotoSuggestion): void {
	window.OCA?.Viewer?.open?.({ path: photo.path, list: selected.value })
}
</script>

<style lang="scss" scoped>
.photo-suggestions {
	display: flex;
	flex-direction: column;
	gap: 16px;
	min-height: 100%;
	padding-inline: 10px;

	&__loading {
		display: flex;
		justify-content: center;
		padding: 32px;
	}

	&__section {
		display: flex;
		flex-direction: column;
		gap: 8px;

		h3 {
			margin: 0;
			font-size: 1em;
			font-weight: bold;
		}
	}

	&__selection-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	&__summary {
		margin: 0;
		font-weight: bold;
	}

	&__selection-actions {
		display: flex;
		gap: 8px;
	}

	&__hint {
		margin: 0;
		color: var(--color-text-maxcontrast);
	}

	&__thumbnail {
		border-radius: var(--border-radius);
		object-fit: cover;
	}

	&__grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
		gap: 4px;
	}

	&__tile {
		position: relative;
		aspect-ratio: 1;
		overflow: hidden;
		border-radius: var(--border-radius-large);

		&:hover,
		&:focus-within {
			.photo-suggestions__tile-actions {
				opacity: 1;
			}
		}
	}

	&__tile-preview {
		width: 100%;
		height: 100%;
		margin: 0;
		padding: 0;
		border: 0;
		border-radius: 0;
		background-color: var(--color-background-dark);
		background-position: center;
		background-size: cover;
		cursor: pointer;
	}

	&__tile-actions {
		position: absolute;
		top: 4px;
		inset-inline-end: 4px;
		opacity: 0;
		border-radius: var(--border-radius-element);
		background-color: rgba(0, 0, 0, .4);
	}

	&__sources,
	&__segments {
		display: flex;
		flex-direction: column;
	}

	&__segments {
		padding-inline-start: 28px;
	}

	&__source {
		display: flex;
		align-items: center;
		gap: 6px;
		min-width: 0;
	}

	&__source-color {
		flex-shrink: 0;
		width: 10px;
		height: 10px;
		border-radius: 50%;
	}

	&__source-name {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__label {
		color: var(--color-text-maxcontrast);
	}

	&__footer {
		position: sticky;
		bottom: 0;
		display: flex;
		flex-wrap: wrap;
		justify-content: flex-end;
		gap: 8px;
		margin-top: auto;
		padding-block: 12px;
		border-top: 1px solid var(--color-border);
		background-color: var(--color-main-background);
	}
}
</style>
