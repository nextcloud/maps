<template>
	<Multiselect
		:options="data" />
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

export default {
	name: 'SearchField',

	components: {
		Multiselect,
	},

	props: {
		data: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	watch: {
	},

	created() {
	},

	methods: {
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
