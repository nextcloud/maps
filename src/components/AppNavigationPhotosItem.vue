<template>
	<AppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-category-multimedia'"
		:title="t('maps', 'My photos')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="false"
		:force-menu="false"
		@click="$emit('photos-clicked')">
		<template slot="counter">
			<span v-if="enabled && photos.length"
				class="photo-counter">
				{{ photos.length }}
			</span>
			&nbsp;
		</template>
		<template slot="actions">
			<ActionButton v-if="enabled && photos.length"
				icon="icon-category-security"
				:close-after-click="false"
				@click="$emit('draggable-clicked')">
				{{ draggable ? t('maps', 'Disable photo drag') : t('maps', 'Enable photo drag') }}
			</ActionButton>
			&nbsp;
		</template>
	</AppNavigationItem>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

export default {
	name: 'AppNavigationPhotosItem',

	components: {
		AppNavigationItem,
		ActionButton,
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
		draggable: {
			type: Boolean,
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

.photo-counter {
	//margin-right: 18px;
}
</style>
