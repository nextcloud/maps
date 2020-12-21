<template>
	<div class="favorite-edition">
		<div class="favorite-form">
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
				<template #singleLabel="{option}">
					<div class="single-label">
						{{ option.catid }}
					</div>
				</template>
			</Multiselect>
			<span class="icon icon-comment" />
			<textarea v-model="comment" :placeholder="commentPH" rows="1" />
		</div>
		<div class="buttons">
			<button @click="onOkClick">
				<span class="icon-checkmark" />
				{{ t('maps', 'Ok') }}
			</button>
			<button @click="onDeleteClick">
				<span class="icon-delete" />
				{{ t('maps', 'Delete') }}
			</button>
		</div>
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

export default {
	name: 'FavoriteEditionForm',

	components: {
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
	},

	data() {
		return {
			name: this.favorite.name,
			category: this.favorite.category,
			comment: this.favorite.comment,
			namePH: t('maps', 'Favorite name'),
			categoryPH: t('maps', 'Category'),
			commentPH: t('maps', 'Comment'),
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
	},

	watch: {
		favorite() {
			this.reset()
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
			const editedFav = {
				...this.favorite,
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
