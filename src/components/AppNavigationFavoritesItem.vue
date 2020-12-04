<template>
	<AppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-favorite'"
		:title="t('maps', 'My favorites')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="true"
		:open="open"
		:force-menu="false"
		@click="onFavoritesClick"
		@update:open="onUpdateOpen">
		<template slot="counter">
			&nbsp;
			<span v-if="enabled && nbFavorites">{{ nbFavorites }}</span>
		</template>
		<template v-if="enabled" slot="actions">
			<ActionButton
				:icon="draggable ? 'icon-hand' : 'icon-hand-slash'"
				:close-after-click="false"
				@click="$emit('draggable-clicked')">
				{{ draggable ? t('maps', 'Disable favorite drag') : t('maps', 'Enable favorite drag') }}
			</ActionButton>
			<ActionButton
				icon="icon-checkmark"
				@click="onToggleAllClick">
				{{ t('maps', 'Toggle all') }}
			</ActionButton>
			<ActionButton
				icon="icon-search"
				:close-after-click="true"
				@click="onZoomAllClick">
				{{ t('maps', 'Zoom') }}
			</ActionButton>
			<ActionButton
				icon="icon-save"
				:close-after-click="true"
				@click="$emit('export')">
				{{ t('maps', 'Export') }}
			</ActionButton>
			<ActionButton
				icon="icon-folder"
				:close-after-click="true"
				@click="$emit('import')">
				{{ t('maps', 'Import') }}
			</ActionButton>
		</template>
		<template #default>
			<AppNavigationItem
				v-for="(c, catid) in categories"
				:key="catid"
				:title="c.name"
				:class="{ 'subitem-disabled': !c.enabled }"
				:editable="c.enabled"
				:edit-placeholder="t('maps', 'Category name')"
				:edit-label="t('maps', 'Rename')"
				:allow-collapse="false"
				:force-menu="false"
				@click="onCategoryClick(catid)"
				@update:title="$emit('rename-category', { old: catid, new: $event })">
				<template #icon>
					<img :src="getIconUrl(c.color)">
				</template>
				<template slot="counter">
					&nbsp;
					<span v-if="enabled && nbFavorites && c.enabled">{{ c.counter }}</span>
				</template>
				<template slot="actions">
					<ActionButton v-if="enabled && nbFavorites && c.enabled"
						icon="icon-search"
						:close-after-click="true"
						@click="onZoomCategoryClick(catid)">
						{{ t('maps', 'Zoom to bounds') }}
					</ActionButton>
					<ActionButton v-if="enabled && nbFavorites && c.enabled"
						icon="icon-save"
						:close-after-click="true"
						@click="$emit('export-category', catid)">
						{{ t('maps', 'Export') }}
					</ActionButton>
					<ActionButton v-if="enabled && nbFavorites && c.enabled"
						icon="icon-delete"
						:close-after-click="true"
						@click="$emit('delete-category', catid)">
						{{ t('maps', 'Delete') }}
					</ActionButton>
				</template>
			</AppNavigationItem>
		</template>
	</AppNavigationItem>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import { generateUrl } from '@nextcloud/router'

import optionsController from '../optionsController'

export default {
	name: 'AppNavigationFavoritesItem',

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
		draggable: {
			type: Boolean,
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
	},

	data() {
		return {
			open: optionsController.optionValues?.favoriteCategoryListShow === 'true',
		}
	},

	computed: {
		nbFavorites() {
			return Object.keys(this.favorites).length
		},
	},

	methods: {
		onFavoritesClick() {
			if (!this.enabled && !this.open) {
				this.open = true
				optionsController.saveOptionValues({ favoriteCategoryListShow: 'true' })
			}
			this.$emit('favorites-clicked')
		},
		onUpdateOpen(isOpen) {
			this.open = isOpen
			optionsController.saveOptionValues({ favoriteCategoryListShow: isOpen ? 'true' : 'false' })
		},
		onToggleAllClick() {
			this.$emit('toggle-all-categories')
		},
		onZoomAllClick() {
			this.$emit('zoom-all-categories')
		},
		onZoomCategoryClick(catid) {
			this.$emit('zoom-category', catid)
		},
		onCategoryClick(catid) {
			this.$emit('category-clicked', catid)
		},
		getIconUrl(color) {
			return generateUrl('/svg/core/actions/star?color=' + color)
		},
	},
}
</script>

<style lang="scss" scoped>
.item-disabled {
	opacity: 0.5;
}

.subitem-disabled {
	opacity: 0.5;
}

::v-deep .icon-hand {
	opacity: 1;
	background-color: black;
	padding: 0 !important;
	mask: url('../../img/hand.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/hand.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
	min-width: 38px !important;
	min-height: 36px !important;
}

::v-deep .icon-hand-slash {
	opacity: 1;
	background-color: black;
	padding: 0 !important;
	mask: url('../../img/hand-slash.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/hand-slash.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
	min-width: 38px !important;
	min-height: 36px !important;
}

::v-deep .icon-save {
	background-color: var(--color-main-text);
	mask: url('../../img/save.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/save.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}
</style>
