<template>
	<AppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-category-multimedia'"
		:title="t('maps', 'My photos')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="false"
		@click="$emit('photos-clicked')">
		<template slot="counter">
			<span v-if="enabled && photos.length">{{ photos.length }}</span>
			&nbsp;
		</template>
	</AppNavigationItem>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'

export default {
	name: 'AppNavigationPhotosItem',

	components: {
		AppNavigationItem,
	},

	props: {
		enabled: {
			type: Boolean,
			required: true,
		},
		loading: {
			type: Boolean,
			default: false,
		},
		photos: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
	},

	methods: {
	},
}
</script>

<style lang="scss" scoped>
.item-disabled {
	opacity: 0.5;
}
</style>
