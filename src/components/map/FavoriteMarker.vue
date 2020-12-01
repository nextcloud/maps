<template>
	<LMarker
		:options="{ data: favorite }"
		:icon="icon"
		:lat-lng="[favorite.lat, favorite.lng]"
		@popupopen="onPopupOpen">
		<LTooltip
			:options="tooltipOptions">
			<div class="tooltip-favorite-wrapper"
				:style="'border: 2px solid #' + color">
				<b>{{ t('maps', 'Name') }}:</b>
				<span>{{ favorite.name || t('maps', 'No name') }}</span>
				<br>
				<b>{{ t('maps', 'Category') }}:</b>
				<span>{{ favorite.category }}</span>
				<br v-if="favorite.comment">
				<b v-if="favorite.comment">{{ t('maps', 'Comment') }}:</b>
				<span v-if="favorite.comment">{{ favorite.comment }}</span>
			</div>
		</LTooltip>
		<LPopup
			class="popup-favorite-wrapper"
			:options="popupOptions">
			<div class="popup-favorite-content">
				<span class="icon icon-favorite" />
				<input
					v-model="name"
					type="text"
					:placeholder="namePH">
				<span class="icon icon-category-organization" />
				<Multiselect
					ref="select"
					v-model="selectedCategory"
					class="category-select"
					label="label"
					track-by="multiselectKey"
					:auto-limit="false"
					:limit="8"
					:options-limit="8"
					:max-height="8 * 45"
					:close-on-select="false"
					:clear-on-select="false"
					:preserve-search="false"
					:placeholder="categoryPH"
					:options="formattedCategories"
					:user-select="false"
					@input="onCategorySelected"
					@search-change="onSearchChange">
					<template #singleLabel="{option}">
						<div class="single-label">
							{{ option.catid }}
						</div>
					</template>
				</Multiselect>
				<span class="icon icon-comment" />
				<textarea v-model="comment" :placeholder="commentPH" rows="1" />
			</div>
			<button @click="onOkClick">
				<span class="icon-checkmark" />
				{{ t('maps', 'Ok') }}
			</button>
			<button @click="onDeleteClick">
				<span class="icon-delete" />
				{{ t('maps', 'Delete') }}
			</button>
		</LPopup>
	</LMarker>
</template>

<script>
// import { generateUrl } from '@nextcloud/router'
// import { getCurrentUser } from '@nextcloud/auth'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

import L from 'leaflet'
import { LMarker, LTooltip, LPopup } from 'vue2-leaflet'

export default {
	name: 'FavoriteMarker',

	components: {
		LMarker,
		LTooltip,
		LPopup,
		Multiselect,
	},

	props: {
		favorite: {
			type: Object,
			required: true,
		},
		categories: {
			type: Object,
			required: true,
		},
		icon: {
			type: Object,
			required: true,
		},
		color: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			name: this.favorite.name,
			category: this.favorite.category,
			comment: this.favorite.comment,
			namePH: t('maps', 'Favorite name'),
			categoryPH: t('maps', 'Category'),
			commentPH: t('maps', 'Comment'),
			tooltipOptions: {
				className: 'leaflet-marker-favorite-tooltip',
				direction: 'top',
				offset: L.point(0, 0),
			},
			popupOptions: {
				closeOnClick: true,
				className: 'popovermenu open popupMarker favoritePopup',
				offset: L.point(-5, 10),
			},
			newCategoryOption: null,
			selectedCategory: null,
		}
	},

	computed: {
		formattedCategories() {
			const categoryOptions = Object.values(this.categories).map((c) => {
				return {
					label: c.name,
					catid: c.name,
					multiselectKey: c.name,
				}
			})
			return this.newCategoryOption
				? [this.newCategoryOption, ...categoryOptions]
				: categoryOptions
		},
	},

	beforeMount() {
	},

	methods: {
		onPopupOpen() {
			// reset form values to favorites values
			this.name = this.favorite.name
			this.category = this.favorite.category
			this.selectedCategory = {
				label: this.favorite.category,
				catid: this.favorite.category,
				multiselectKey: this.favorite.category,
			}
			this.comment = this.favorite.comment
		},
		onSearchChange(query) {
			if (query === '' || Object.keys(this.categories).includes(query)) {
				this.newCategoryOption = null
			} else {
				this.newCategoryOption = {
					label: t('maps', 'New category {n}', { n: query }),
					catid: query,
					multiselectKey: query,
				}
			}
		},
		onCategorySelected(option) {
			this.category = option.catid
		},
		onOkClick() {
			console.debug('edit fav ' + this.name + ' ' + this.category + ' ' + this.comment)
			const editedFav = {
				id: this.favorite.id,
				name: this.name,
				category: this.category,
				comment: this.comment,
			}
			this.$emit('edit', editedFav)
		},
		onDeleteClick() {
			this.$emit('delete', this.favorite.id)
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
.multiselect {
	min-width: 0px;
}

.tooltip-favorite-wrapper {
	padding: 6px;
	border-radius: 3px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}

.popup-favorite-content {
	display: grid;
	grid-template: 1fr / 40px 1fr;
}

.popup-favorite-wrapper {
	button {
		margin-left: auto;
		margin-right: auto;
	}
}
</style>
