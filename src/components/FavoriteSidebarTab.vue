<template>
	<div v-if="favorite">
		{{ favorite.name }}
	</div>
	<div v-else>
		NOFAV
	</div>
</template>

<script>

export default {
	name: 'FavoriteSidebarTab',

	components: {
		// ActionButton,
		// ActionLink,
	},

	props: {
		favorite: {
			required: true,
			validator: prop => typeof prop === 'object' || prop === null,
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
