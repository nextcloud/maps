<template>
	<div id="photo-suggestions-tab">
		<div v-if="loading" class="icon-loading" />
		<div v-else-if="photoSuggestions.length > 0">
			<Button
				v-show="photoSuggestionsSelectedIndices.length === 0"
				@click="$emit('select-some')">
				{{ t('maps', 'Select some') }}
			</Button>
			<Button
				@click="$emit('clear-selection')">
				{{ t('maps', 'Clear selection') }}
			</Button>
			<Button
				type="primary"
				@click="$emit('select-all')">
				{{ t('maps', 'Select all') }}
			</Button>
			<div v-if="photoSuggestionsSelectedIndices.length > 0">
				<table class="photoSuggestionsSelected-table">
					<tr v-for="p in photoSuggestionsSelected"
						:key="p.fileid">
						<td>
							<img
								:src="previewUrl(p)"
								:alt="p.basename"
								width="64"
								height="64">
						</td>
						<td>
							{{ p.basename }}
						</td>
					</tr>
				</table>
			</div>
		</div>
		<div v-else>
			<h2> {{ t('maps','No Suggestions found') }} </h2>
			{{ t('maps','To get suggestions upload tracks from the trips, when you took your photos.'
				+ 'For future trips you can track your android phone using phonetrack.'
				+ 'This information are then automatically used suggest photo location') }}
		</div>
		<Button
			@click="$emit('cancel')">
			{{ t('maps', 'cancel') }}
		</Button>
		<Button
			v-show="photoSuggestions.length > 0"
			type="primary"
			:disabled="photoSuggestionsSelectedIndices.length===0"
			@click="$emit('save')">
			{{ t('maps', 'save') }}
		</Button>
	</div>
</template>

<script>

import { generateUrl } from '@nextcloud/router'
import Button from '@nextcloud/vue/dist/Components/Button'

export default {
	name: 'PhotoSuggestionsSidebarTab',

	components: {
		Button,
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
			return this.photoSuggestionsSelectedIndices.map((i) => this.photoSuggestions[i])
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
