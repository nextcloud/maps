<template>
	<NcAppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-favorite'"
		:name="t('maps', 'My favorites')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="true"
		:open="open"
		:force-menu="false"
		@click="onFavoritesClick"
		@update:open="onUpdateOpen">
		<NcCounterBubble v-show="enabled && nbFavorites"
			slot="counter">
			{{ nbFavorites > 99 ? '99+' : nbFavorites }}
		</NcCounterBubble>
		<template v-if="enabled" slot="actions">
			<NcActionButton v-if="!readOnly"
				:icon="draggable ? 'icon-hand' : 'icon-hand-slash'"
				:close-after-click="false"
				@click="$emit('draggable-clicked')">
				{{ draggable ? t('maps', 'Disable favorite drag') : t('maps', 'Enable favorite drag') }}
			</NcActionButton>
			<NcActionButton
				icon="icon-checkmark"
				@click="onToggleAllClick">
				{{ t('maps', 'Toggle all') }}
			</NcActionButton>
			<NcActionButton
				icon="icon-search"
				:close-after-click="true"
				@click="onZoomAllClick">
				{{ t('maps', 'Zoom') }}
			</NcActionButton>
			<NcActionButton v-if="!isPublic"
				icon="icon-save"
				:close-after-click="true"
				@click="$emit('export')">
				{{ t('maps', 'Export') }}
			</NcActionButton>
			<NcActionButton v-if="!readOnly && !isPublic"
				icon="icon-folder"
				:close-after-click="true"
				@click="$emit('import')">
				{{ t('maps', 'Import') }}
			</NcActionButton>
		</template>
		<template slot="default">
			<NcAppNavigationNew
				v-if="enabled && !readOnly"
				:text="addFavoriteText"
				:button-class="addFavoriteIcon"
				@click="onAddFavoriteClick" />
			<NcAppNavigationItem
				v-for="(c, catid) in categories"
				:key="catid"
				:name="c.name"
				:class="{ 'subitem-disabled': !c.enabled }"
				:editable="enabled && c.enabled && c.isUpdateable"
				:edit-placeholder="t('maps', 'Category name')"
				:edit-label="t('maps', 'Rename')"
				:allow-collapse="false"
				:force-menu="false"
				@click="onCategoryClick(catid)"
				@update:name="$emit('rename-category', { old: catid, new: $event })">
				<template #icon>
					<div :class="{ favoriteMarker: true, navigationFavoriteMarkerDark: isDarkTheme, navigationFavoriteMarker: !isDarkTheme }"
						:style="'background-color: #' + c.color" />
				</template>
				<NcCounterBubble v-show="enabled && nbFavorites && c.enabled"
					slot="counter">
					{{ c.counter > 99 ? '99+' : c.counter }}
				</NcCounterBubble>
				<template slot="actions">
					<NcActionButton v-if="enabled && nbFavorites && c.enabled && c.isUpdateable"
						icon="icon-add"
						:close-after-click="true"
						@click="onAddFavoriteClick(catid)">
						{{ t('maps', 'Add a favorite') }}
					</NcActionButton>
					<NcActionButton v-if="enabled && nbFavorites && c.enabled"
						icon="icon-search"
						:close-after-click="true"
						@click="onZoomCategoryClick(catid)">
						{{ t('maps', 'Zoom to bounds') }}
					</NcActionButton>
					<NcActionCheckbox v-if="enabled && nbFavorites && c.enabled && c.name && c.name !== t('maps', 'Personal') && c.isShareable"
						:checked="c.token && c.token !== ''"
						:close-after-click="false"
						@update:checked="$emit('category-share-change', catid, $event)">
						{{ c.token ? t('maps', 'Delete share link') : t('maps', 'Create share link') }}
					</NcActionCheckbox>
					<NcActionButton v-if="enabled && nbFavorites && c.enabled && c.token"
						icon="icon-clippy"
						:close-after-click="false"
						@click="onShareLinkCopy(c)">
						{{ isLinkCopied[catid] ? t('maps', 'Copied!') : t('maps', 'Copy share link') }}
					</NcActionButton>
					<NcActionButton v-if="enabled && nbFavorites && c.enabled"
						icon="icon-save"
						:close-after-click="true"
						@click="$emit('export-category', catid)">
						{{ t('maps', 'Export') }}
					</NcActionButton>
					<NcActionButton v-if="enabled && nbFavorites && c.enabled && !isPublic()"
						icon="icon-share"
						:close-after-click="true"
						@click="$emit('add-to-map-category', catid)">
						{{ c.token ? t('maps', 'Link to map') : t('maps', 'Copy to map') }}
					</NcActionButton>
					<NcActionButton v-if="enabled && nbFavorites && c.enabled && c.isDeletable"
						icon="icon-delete"
						:close-after-click="true"
						@click="$emit('delete-category', catid)">
						{{ t('maps', 'Delete') }}
					</NcActionButton>
					<NcActionButton v-if="enabled && nbFavorites && c.enabled && c.token && !c.isShareable"
						icon="icon-delete"
						:close-after-click="true"
						@click="$emit('delete-shared-category-from-map', catid)">
						{{ t('maps', 'Leave share') }}
					</NcActionButton>
				</template>
			</NcAppNavigationItem>
		</template>
	</NcAppNavigationItem>
</template>

<script>
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppNavigationNew from '@nextcloud/vue/dist/Components/NcAppNavigationNew.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import { isPublic } from '../utils/common.js'

import optionsController from '../optionsController.js'

export default {
	name: 'AppNavigationFavoritesItem',

	components: {
		NcAppNavigationItem,
		NcAppNavigationNew,
		NcActionButton,
		NcActionCheckbox,
		NcCounterBubble,
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
		addingFavorite: {
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
		addFavoriteText() {
			return this.addingFavorite
				? t('maps', 'Cancel')
				: t('maps', 'Add a favorite')
		},
		addFavoriteIcon() {
			return this.addingFavorite
				? 'icon-history'
				: 'icon-add'
		},
		readOnly() {
			const farray = Object.values(this.favorites)
			return !farray.some((f) => (f.isUpdateable))
			&& !(farray.length === 0 && optionsController.optionValues?.isCreatable)
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
				try {
					await navigator.clipboard.writeText(url)
					showSuccess(t('maps', 'Link copied'))
				} catch (error) {
					console.debug(error)
					showError(t('maps', 'Link {url} could not be copied to clipboard.', { url }))
				}
				this.$set(this.isLinkCopied, category.name, true)
				setTimeout(() => {
					this.$delete(this.isLinkCopied, category.name)
				}, 5000)
			} catch (error) {
				console.debug(error)
				showError(t('maps', 'Link could not be copied to clipboard.'))
			}
		},
		onAddFavoriteClick(category = null) {
			this.$emit('add-favorite', category)
		},
		isPublic() {
			return isPublic()
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
