<template>
	<div id="photo-suggestions-tab">
		<div v-if="loading" class="icon-loading" />
		<div v-else-if="photoSuggestions.length > 0">
			<div class="oc-dialog-buttonrow">
				<Button
					@click="$emit('clear-selection')">
					{{ t('maps', 'Clear selection') }}
				</Button>
				<Button
					type="primary"
					@click="$emit('select-all')">
					{{ t('maps', 'Select all') }}
				</Button>
			</div>
			<div v-if="photoSuggestionsSelectedIndices.length > 0">
				<ListItem v-for="p in photoSuggestionsSelected"
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
						<ActionButton @click="onListItemClick(p)">
							{{ t('maps', 'Display picture') }}
						</ActionButton>
						<ActionButton @click="$emit('save',[p.photoSuggestionsIndex])">
							{{ t('maps', 'Save') }}
						</ActionButton>
						<ActionButton @click="$emit('zoom', p)">
							{{ t('maps', 'Zoom') }}
						</ActionButton>
						<ActionButton @click="$emit('clear-selection',[p.photoSuggestionsIndex])">
							{{ t('maps', 'Remove form selection') }}
						</ActionButton>
					</template>
				</ListItem>
			</div>
		</div>
		<div v-else>
			<h2> {{ t('maps','No Suggestions found') }} </h2>
			{{ t('maps','To get suggestions upload tracks from the trips, when you took your photos.'
				+ 'For future trips you can track your android phone using phonetrack.'
				+ 'This information are then automatically used suggest photo location') }}
		</div>
		<div class="oc-dialog-buttonrow">
			<Button
				@click="$emit('cancel')">
				{{ !photoSuggestions.includes(null) ? t('maps', 'cancel') : t('maps', 'quit') }}
			</Button>
			<Button
				v-show="photoSuggestions.length > 0"
				type='primary'
				:disabled="photoSuggestionsSelectedIndices.length===0"
				@click="$emit('save')">
				{{ t('maps', 'save') }}
			</Button>
		</div>
	</div>
</template>

<script>

import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import Button from '@nextcloud/vue/dist/Components/Button'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ListItem from '@nextcloud/vue/dist/Components/ListItem'

export default {
	name: 'PhotoSuggestionsSidebarTab',

	components: {
		Button,
		ActionButton,
		ListItem,
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
		loading: {
			required: true,
			type: Boolean,
		},
	},

	data() {
		return {
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
	},

	watch: {
	},

	methods: {
		previewUrl(photo) {
			return photo.hasPreview
				? generateUrl('core') + '/preview?fileId=' + photo.fileId + '&x=500&y=300&a=1'
				: generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
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
</style>
