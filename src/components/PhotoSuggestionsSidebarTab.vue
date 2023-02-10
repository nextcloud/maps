<template>
	<div id="photo-suggestions-tab">
		<div v-if="loading" class="icon-loading" />
		<div v-else-if="photoSuggestions.length > 0">
			<div class="oc-dialog-buttonrow">
				<NcButton
					@click="$emit('clear-selection')">
					{{ t('maps', 'Clear selection') }}
				</NcButton>
				<NcButton
					type="primary"
					@click="$emit('select-all')">
					{{ t('maps', 'Select all') }}
				</NcButton>
				<NcTimezonePicker
					:value="photoSuggestionsTimezone"
					@input="$emit('change-timezone', $event)" />
				<NcActions>
					<NcActionButton
						:icon="selectionLayout==='list'?'icon-toggle-pictures':'icon-toggle-filelist'"
						@click="selectionLayout=selectionLayout==='list'?'grid':'list'">
						{{ t('maps', selectionLayout==='list'?'grid view':'list view') }}
					</NcActionButton>
				</NcActions>
			</div>
			<div v-if="photoSuggestionsSelectedIndices.length > 0 && selectionLayout==='list'">
				<NcListItem v-for="p in photoSuggestionsSelected"
					:key="p.photoSuggestionsIndex"
					:title="p.basename"
					:bold="false"
					:details="getPhotoFormattedDate(p)"
					@click="onListItemClick(p)">
					<template #icon>
						<img
							:src="previewUrl(p)"
							:alt="p.basename"
							width="64"
							height="64">
					</template>
					<template #subtitle>
						{{ p.path }}
					</template>
					<template #actions>
						<NcActionButton @click="onListItemClick(p)">
							{{ t('maps', 'Display picture') }}
						</NcActionButton>
						<NcActionButton @click="$emit('save',[p.photoSuggestionsIndex])">
							{{ t('maps', 'Save') }}
						</NcActionButton>
						<NcActionButton @click="$emit('zoom', p)">
							{{ t('maps', 'Zoom') }}
						</NcActionButton>
						<NcActionButton @click="$emit('clear-selection',[p.photoSuggestionsIndex])">
							{{ t('maps', 'Remove form selection') }}
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
			<div v-if="photoSuggestionsSelectedIndices.length > 0 && selectionLayout==='grid'"
				class="photo-suggestion-selected-grid">
				<div v-for="p in photoSuggestionsSelected"
					:key="p.photoSuggestionsIndex"
					class="photo-suggestion-selected-grid-item"
					:style="{
						'background-image': 'url('+previewUrl(p)+')',
						'background-size': 'cover'}"
					@click.self="onListItemClick(p)">
					<NcActions class="photo-suggestion-selected-grid-actions">
						<NcActionButton @click="onListItemClick(p)">
							{{ t('maps', 'Display picture') }}
						</NcActionButton>
						<NcActionButton @click="$emit('save',[p.photoSuggestionsIndex])">
							{{ t('maps', 'Save') }}
						</NcActionButton>
						<NcActionButton @click="$emit('zoom', p)">
							{{ t('maps', 'Zoom') }}
						</NcActionButton>
						<NcActionButton @click="$emit('clear-selection',[p.photoSuggestionsIndex])">
							{{ t('maps', 'Remove form selection') }}
						</NcActionButton>
					</NcActions>
				</div>
			</div>
		</div>
		<div v-else>
			<h2> {{ t('maps','No suggestions found') }} </h2>
			{{ t('maps','To get suggestions upload tracks from the trips, when you took your photos.'
				+ 'For future trips you can track your android phone using phonetrack.'
				+ 'This information are then automatically used suggest photo location') }}
		</div>
		<div class="oc-dialog-buttonrow">
			<NcButton
				@click="$emit('cancel')">
				{{ !photoSuggestions.includes(null) ? t('maps', 'Cancel') : t('maps', 'Quit') }}
			</NcButton>
			<NcButton
				@click="$emit('load-more')">
				{{ t('maps', 'Load more') }}
			</NcButton>
			<NcButton
				v-show="photoSuggestions.length > 0"
				type="primary"
				:disabled="photoSuggestionsSelectedIndices.length===0 || readOnly"
				@click="$emit('save')">
				{{ t('maps', 'Save') }}
			</NcButton>
		</div>
	</div>
</template>

<script>

import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import NcButton from '@nextcloud/vue/dist/Components/NcButton'
import NcActions from '@nextcloud/vue/dist/Components/NcActions'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem'
import optionsController from '../optionsController'
import { getToken } from '../utils/common'
import NcTimezonePicker from '@nextcloud/vue/dist/Components/NcTimezonePicker'

export default {
	name: 'PhotoSuggestionsSidebarTab',

	components: {
		NcButton,
		NcActions,
		NcActionButton,
		NcListItem,
		NcTimezonePicker,
	},

	props: {
		photoSuggestions: {
			required: true,
			type: Array,
		},
		photoSuggestionsSelectedIndices: {
			required: true,
			type: Array,
		},
		photoSuggestionsTimezone: {
			reuired: true,
			type: String,
		},
		loading: {
			required: true,
			type: Boolean,
		},
	},

	data() {
		return {
			selectionLayout: 'list',
		}
	},

	computed: {
		photoSuggestionsSelected() {
			return this.photoSuggestionsSelectedIndices.reduce((filtered, i) => {
				const p = this.photoSuggestions[i]
				if (p) {
					p.photoSuggestionsIndex = i
					filtered.push(p)
				}
				return filtered
			}, [])
		},
		readOnly() {
			return !this.photoSuggestions.some((f) => (f.isUpdateable))
		},
	},

	watch: {
	},

	methods: {
		previewUrl(photo) {
			if (photo && photo.hasPreview) {
				const token = getToken()
				return token
					? generateUrl('apps/files_sharing/publicpreview/') + token + '?file=' + encodeURIComponent(photo.path) + '&x=341&y=256&a=1'
					: generateUrl('core') + '/preview?fileId=' + photo.fileId + '&x=341&y=256&a=1'
			} else {
				return generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
			}
		},
		getPhotoFormattedDate(photo) {
			return moment.unix(photo.dateTaken).format('L')
		},
		onListItemClick(photo) {
			if (OCA.Viewer && OCA.Viewer.open) {
				OCA.Viewer.open({ path: photo.path, list: this.photoSuggestionsSelected })
			}
		},
	},
}
</script>

<style lang="scss" scoped>
#photo-tab {
	padding: 0 10px 0 10px;
	.thumbnail {
		opacity: 1;
		background-color: var(--color-main-text);
		padding: 0 !important;
		mask-size: 64px auto;
		mask-position: center;
		-webkit-mask-size: 64px auto;
		-webkit-mask-position: center;
	}
}
.photo-suggestion-selected-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(128px, 1fr));
	grid-auto-rows: 1fr;
	gap: 5px;
	padding-left: 5px;
}

.photo-suggestion-selected-grid-item {
	padding-bottom: calc(100% - 44px);
}

.photo-suggestion-selected-grid-actions {
	position: absolute;
	top:      0;
	left:     calc(100% - 44px);
	bottom:   0;
	right:    0;
	opacity: 0;
}

.photo-suggestion-selected-grid-item:hover .photo-suggestion-selected-grid-actions {
	opacity: 1 !important;
}

</style>
