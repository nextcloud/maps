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

<script setup>
import { computed } from 'vue'

import FavoriteMarker from './FavoriteMarker.vue'

const props = defineProps({
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
})

defineEmits(['click', 'add-to-map-favorite', 'edit', 'delete'])

const displayedFavorites = computed(() =>
	Object.values(props.favorites).filter(f => props.categories[f.category]?.enabled),
)
</script>
