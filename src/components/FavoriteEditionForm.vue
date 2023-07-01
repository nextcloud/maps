<template>
	<div class="favorite-edition">
		<div class="favorite-form">
			<span class="icon icon-favorite" />
			<input
				v-model="name"
				type="text"
				:placeholder="namePH"
				:readonly="!favorite.isUpdateable">
			<span class="icon icon-category-organization" />
			<NcMultiselect v-if="favorite.isUpdateable"
				ref="select"
				v-model="selectedCategory"
				class="category-select"
				label="label"
				track-by="multiselectKey"
				open-direction="bottom"
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
				<template #singleLabel="{ option }">
					<div class="single-label">
						{{ option ? option.catid : '' }}
					</div>
				</template>
			</NcMultiselect>
			<input v-else
				v-model="selectedCategory.catid"
				type="text"
				:placeholder="namePH"
				:readonly="!favorite.isUpdateable">
			<span class="icon icon-comment" />
			<textarea v-model="comment"
				:placeholder="commentPH"
				:readonly="!favorite.isUpdateable"
				rows="1" />
			<span class="icon icon-address" />
			<input
				v-model="location"
				type="text"
				:placeholder="locationPH"
				:readonly="!favorite.isUpdateable">
		</div>
		<div class="buttons">
			<NcButton
				:disabled="!favorite.isUpdateable"
				native-type="submit"
				type="primary"
				@click="onOkClick">
				<template>
					{{ t('maps', 'OK') }}
				</template>
			</NcButton>
			<NcButton :disabled="!favorite.isUpdateable"
				@click="onDeleteClick">
				<template>
					{{ t('maps', 'Delete') }}
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import NcMultiselect from '@nextcloud/vue/dist/Components/NcMultiselect'
import NcButton from '@nextcloud/vue/dist/Components/NcButton'

export default {
	name: 'FavoriteEditionForm',

	components: {
		NcMultiselect,
		NcButton,
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
	},

	data() {
		return {
			name: this.favorite.name,
			category: this.favorite.category,
			comment: this.favorite.comment,
			lat: this.favorite.lat,
			lng: this.favorite.lng,
			namePH: t('maps', 'Favorite name'),
			categoryPH: t('maps', 'Category'),
			commentPH: t('maps', 'Comment'),
			locationPH: t('maps', 'Location'),
			newCategoryOption: null,
			selectedCategory: {
				label: this.favorite.category,
				catid: this.favorite.category,
				multiselectKey: this.favorite.category,
			},
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
		location: {
			get() {
				return `${this.lat},${this.lng}`
			},
			set(value) {
				const [lat, lng] = value.split(',')
				this.lat = lat
				this.lng = lng
			},
		},
	},

	watch: {
		favorite: {
			handler() {
				this.reset()
			},
			deep: true,
		},
	},

	methods: {
		reset() {
			// reset form values to favorites values
			this.name = this.favorite.name
			this.category = this.favorite.category
			this.selectedCategory = {
				label: this.favorite.category,
				catid: this.favorite.category,
				multiselectKey: this.favorite.category,
			}
			this.comment = this.favorite.comment
			this.lat = this.favorite.lat
			this.lng = this.favorite.lng
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
			this.category = option ? option.catid : ''
		},
		onOkClick() {
			const editedFav = {
				...this.favorite,
				name: this.name,
				category: this.category,
				comment: this.comment,
				lat: this.lat,
				lng: this.lng,
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
.multiselect {
	min-width: 0px;
}

.favorite-form {
	display: grid;
	grid-template: 1fr / 40px 1fr;

	input,
	textarea {
		width: 100%;
	}

	span,
	input,
	textarea,
	.multiselect {
		margin-top: 10px;
	}
}

.buttons {
	margin-top: 15px;
}

.favorite-edition {
	.buttons {
		display: flex;
		button {
			margin-left: auto;
			margin-right: auto;
		}
	}
}
</style>
