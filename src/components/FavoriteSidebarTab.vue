<template>
	<div v-if="favorite"
		id="favorite-tab">
		<FavoriteEditionForm
			ref="form"
			:favorite="favorite"
			:categories="categories"
			@edit="$emit('edit', $event)"
			@delete="$emit('delete', $event)" />
	</div>
</template>

<script>
import FavoriteEditionForm from './FavoriteEditionForm.vue'

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
	},

	methods: {
	},
}
</script>

<style lang="scss" scoped>
#favorite-tab {
	padding: 0 10px 0 10px;
}
</style>
