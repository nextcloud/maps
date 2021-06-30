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
		<CounterBubble v-if="enabled && nbFavorites"
			slot="counter">
			{{ nbFavorites > 99 ? '99+' : nbFavorites }}
		</CounterBubble>
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
				:editable="enabled && c.enabled"
				:edit-placeholder="t('maps', 'Category name')"
				:edit-label="t('maps', 'Rename')"
				:allow-collapse="false"
				:force-menu="false"
				@click="onCategoryClick(catid)"
				@update:title="$emit('rename-category', { old: catid, new: $event })">
				<template #icon>
					<div :class="{ favoriteMarker: true, favoriteMarkerDark: isDarkTheme }"
						:style="'background-color: #' + c.color" />
				</template>
				<CounterBubble v-if="enabled && nbFavorites && c.enabled"
					slot="counter">
					{{ c.counter > 99 ? '99+' : c.counter }}
				</CounterBubble>
				<template slot="actions">
					<ActionButton v-if="enabled && nbFavorites && c.enabled"
						icon="icon-search"
						:close-after-click="true"
						@click="onZoomCategoryClick(catid)">
						{{ t('maps', 'Zoom to bounds') }}
					</ActionButton>
					<ActionCheckbox v-if="enabled && nbFavorites && c.enabled"
						:checked="c.token && c.token !== ''"
						:close-after-click="false"
						@update:checked="$emit('category-share-change', catid, $event)">
						{{ c.token ? t('maps', 'Delete share link') : t('maps', 'Create share link') }}
					</ActionCheckbox>
					<ActionButton v-if="enabled && nbFavorites && c.enabled && c.token"
						icon="icon-clippy"
						:close-after-click="false"
						@click="onShareLinkCopy(c)">
						{{ isLinkCopied[catid] ? t('maps', 'Copied!') : t('maps', 'Copy share link') }}
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
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import CounterBubble from '@nextcloud/vue/dist/Components/CounterBubble'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

import optionsController from '../optionsController'

export default {
	name: 'AppNavigationFavoritesItem',

	components: {
		AppNavigationItem,
		ActionButton,
		ActionCheckbox,
		CounterBubble,
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
			isLinkCopied: {},
			isDarkTheme: OCA.Accessibility?.theme === 'dark',
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
		async onShareLinkCopy(category) {
			try {
				const url = window.location.origin + generateUrl('/apps/maps/s/favorites/' + category.token)
				await this.$copyText(url)
				showSuccess(t('maps', 'Link copied!'))
				this.$set(this.isLinkCopied, category.name, true)
				setTimeout(() => {
					this.$delete(this.isLinkCopied, category.name)
				}, 5000)
			} catch (error) {
				console.debug(error)
				showError(t('maps', 'Link could not be copied to clipboard.'))
			}
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
	background-color: var(--color-main-text);
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
	background-color: var(--color-main-text);
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
