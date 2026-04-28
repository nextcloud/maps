<template>
	<template>
		<FavoriteMarker v-for="f in displayedFavorites"
			:key="f.id + f.name + f.category"
			:favorite="f"
			:categories="categories"
			:draggable="draggable"
			:color="categories[f.category].color"
			@click="$emit('click', $event)"
			@add-to-map-favorite="$emit('add-to-map-favorite', $event)"
			@edit="$emit('edit', $event)"
			@delete="$emit('delete', $event)" />
	</template>
</template>

<script>
import FavoriteMarker from './FavoriteMarker.vue'
import optionsController from '../../optionsController.js'

export default {
	name: 'FavoritesLayer',
	components: {
		FavoriteMarker,
	},

	props: {
		map: {
			type: Object,
			required: true,
		},
		favorites: {
			type: Object,
			required: true,
		},
		categories: {
			type: Object,
			required: true,
		},
		draggable: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			optionValues: optionsController.optionValues,
		}
	},

	computed: {
		displayedFavorites() {
			return Object.values(this.favorites).filter((f) => {
				return this.categories[f.category]?.enabled
			})
		},
	},
}
</script>
