<template>
	<div v-if="favorite">
		<FavoriteEditionForm
			ref="form"
			:favorite="favorite"
			:categories="categories"
			@edit="$emit('edit', $event)"
			@delete="$emit('delete', $event)" />
	</div>
	<div v-else>
		NOFAV
	</div>
</template>

<script>
import FavoriteEditionForm from './FavoriteEditionForm'

export default {
	name: 'FavoriteSidebarTab',

	components: {
		FavoriteEditionForm,
	},

	props: {
		favorite: {
			required: true,
			validator: prop => typeof prop === 'object' || prop === null,
		},
		categories: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
	},

	watch: {
		favorite() {
			console.debug(this.favorite)
		},
	},

	methods: {
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
