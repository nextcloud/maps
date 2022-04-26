<template>
	<AppSidebar v-show="show"
		v-bind="appSidebar"
		@update:active="onActiveChanged"
		@update:starred="toggleStarred"
		@[defaultActionListener].stop.prevent="onDefaultAction"
		@opening="handleOpening"
		@opened="handleOpened"
		@closing="handleClosing"
		@closed="handleClosed"
		@close="$emit('close')">
		<FavoriteSidebarTab v-if="activeTab === 'favorite'"
			:favorite="favorite"
			:categories="favoriteCategories"
			@edit="$emit('edit-favorite', $event)"
			@delete="$emit('delete-favorite', $event)" />
		<!-- TODO: create a standard to allow multiple elements here? -->
		<template v-if="fileInfo" #description>
			<LegacyView v-for="view in views"
				:key="view.cid"
				:component="view"
				:file-info="fileInfo" />
		</template>

		<!-- Actions menu -->
		<template v-if="fileInfo" #secondary-actions>
			<!-- TODO: create proper api for apps to register actions
			And inject themselves here. -->
			<ActionButton v-if="isSystemTagsEnabled"
				:close-after-click="true"
				icon="icon-tag"
				@click="toggleTags">
				{{ t('files', 'Tags') }}
			</ActionButton>
		</template>

		<!-- Error display -->
		<EmptyContent v-if="error" icon="icon-error">
			{{ error }}
		</EmptyContent>

		<!-- If fileInfo fetch is complete, render tabs -->
		<template v-for="tab in tabs" v-else-if="fileInfo">
			<!-- Hide them if we're loading another file but keep them mounted -->
			<SidebarTab v-if="tab.enabled(fileInfo)"
				:id="tab.id"
				:key="tab.id"
				:name="tab.name"
				:icon="tab.icon"
				:on-mount="tab.mount"
				:on-update="tab.update"
				:on-destroy="tab.destroy"
				:on-scroll-bottom-reached="tab.scrollBottomReached"
				:file-info="fileInfo" />
		</template>
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import { emit } from '@nextcloud/event-bus'
import { generateUrl, generateFilePath } from '@nextcloud/router'

import FavoriteSidebarTab from '../components/FavoriteSidebarTab'
import SidebarTab from '../../../../apps/files/src/components/SidebarTab'
import LegacyView from '../../../../apps/files/src/components/LegacyView'
import { encodePath } from '@nextcloud/paths'
import moment from '@nextcloud/moment'
import { Type as ShareTypes } from '@nextcloud/sharing'
import axios from '@nextcloud/axios'
import FileInfo from '../../../../apps/files/src/services/FileInfo'

export default {
	name: 'Sidebar',

	components: {
		// ActionButton,
		AppSidebar,
		FavoriteSidebarTab,
		SidebarTab,
		LegacyView,
		ActionButton,
		EmptyContent,

	},

	props: {
		show: {
			type: Boolean,
			required: true,
		},
		favorite: {
			validator: prop => typeof prop === 'object' || prop === null,
			required: true,
		},
		favoriteCategories: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			Sidebar: OCA.Files.Sidebar.state,
			fileInfo: null,
			error: false,
			isFullScreen: false,
			typeOpened: '',
		}
	},

	computed: {
		/**
		 * Current filename
		 * This is bound to the Sidebar service and
		 * is used to load a new file
		 *
		 * @return {string}
		 */
		file() {
			return this.Sidebar.file
		},

		/**
		 * List of all the registered tabs
		 *
		 * @return {Array}
		 */
		tabs() {
			if (this.activeTab === 'favorite') {
				return []
			}
			return this.Sidebar.tabs
		},

		/**
		 * List of all the registered views
		 *
		 * @return {Array}
		 */
		views() {
			return this.Sidebar.views
		},

		/**
		 * Current user dav root path
		 *
		 * @return {string}
		 */
		davPath() {
			const user = OC.getCurrentUser().uid
			return OC.linkToRemote(`dav/files/${user}${encodePath(this.file)}`)
		},

		/**
		 * Current active tab handler
		 *
		 * @return {string} the current active tab
		 */
		activeTab() {
			return this.Sidebar.activeTab
		},

		/**
		 * Sidebar subtitle
		 *
		 * @return {string}
		 */
		subtitle() {
			return `${this.size}, ${moment(this.fileInfo.mtime).fromNow()}`
		},

		/**
		 * File last modified full string
		 *
		 * @return {string}
		 */
		fullTime() {
			return moment(this.fileInfo.mtime).format('LLL')
		},

		/**
		 * File size formatted string
		 *
		 * @return {string}
		 */
		size() {
			return OC.Util.humanFileSize(this.fileInfo.size)
		},

		/**
		 * File background/figure to illustrate the sidebar header
		 *
		 * @return {string}
		 */
		background() {
			const iconColor = OCA.Accessibility?.theme === 'dark' ? 'ffffff' : '000000'
			if (this.typeOpened === 'track') {
				return generateUrl('/svg/maps/road?color=' + iconColor)
			}
			if (this.typeOpened === 'maps') {
				return generateFilePath('maps', 'img', 'maps.png')
			}
			return this.getPreviewIfAny(this.fileInfo)
		},

		/**
		 * App sidebar v-binding object
		 *
		 * @return {object}
		 */
		appSidebar() {
			if (this.fileInfo) {
				return {
					'data-mimetype': this.fileInfo.mimetype,
					'star-loading': this.starLoading,
					active: this.activeTab,
					background: this.background,
					class: {
						'app-sidebar--has-preview': this.fileInfo.hasPreview && !this.isFullScreen,
						'app-sidebar--full': this.isFullScreen,
					},
					compact: !this.fileInfo.hasPreview || this.isFullScreen,
					loading: this.loading,
					starred: this.fileInfo.isFavourited,
					subtitle: this.subtitle,
					subtitleTooltip: this.fullTime,
					title: this.fileInfo.name,
					titleTooltip: this.fileInfo.name,
				}
			} else if (this.error) {
				return {
					key: 'error', // force key to re-render
					subtitle: '',
					title: '',
				}
			} else if (this.loading) {
				// no fileInfo yet, showing empty data
				return {
					loading: this.loading,
					subtitle: t('maps', 'Shows cool information'),
					title: t('maps', 'Sidebar'),
				}
			} else if (this.activeTab === 'favorite') {
				const iconColor = OCA.Accessibility?.theme === 'dark' ? 'ffffff' : '000000'
				return {
					title: t('maps', 'Favorite'),
					compact: true,
					background: generateUrl('/svg/core/actions/star?color=' + iconColor),
					subtitle: '',
					active: this.activeTab,
					class: {
						'app-sidebar--has-preview': false,
						'app-sidebar--full': this.isFullScreen,
					},
				}
			} else {
				return {
					loading: false,
					subtitle: t('maps', 'Shows cool information'),
					title: t('maps', 'Sidebar'),
				}
			}
		},

		/**
		 * Default action object for the current file
		 *
		 * @return {object}
		 */
		defaultAction() {
			return this.fileInfo
				&& OCA.Files && OCA.Files.App && OCA.Files.App.fileList
				&& OCA.Files.App.fileList.fileActions
				&& OCA.Files.App.fileList.fileActions.getDefaultFileAction
				&& OCA.Files.App.fileList
					.fileActions.getDefaultFileAction(this.fileInfo.mimetype, this.fileInfo.type, OC.PERMISSION_READ)

		},

		/**
		 * Dynamic header click listener to ensure
		 * nothing is listening for a click if there
		 * is no default action
		 *
		 * @return {string|null}
		 */
		defaultActionListener() {
			return this.defaultAction ? 'figure-click' : null
		},

		isSystemTagsEnabled() {
			return OCA && 'SystemTags' in OCA
		},
	},

	/*
	sidebarTitle() {
			if (this.activeTab === 'track') {
				return t('maps', 'Track')
			} else if (this.activeTab === 'favorite') {
				return t('maps', 'Favorite')
			} else if (this.activeTab === 'photo') {
				return this.photo.basename
			} else if (this.activeTab === 'myMaps') {
				return this.myMap.name
			}
			return t('maps', 'Sidebar')
		},
		sidebarSubtitle() {
			if (this.activeTab === 'track') {
				return ''
			} else if (this.activeTab === 'favorite') {
				return ''
			} else if (this.activeTab === 'photo') {
				return this.photo.filename
			} else if (this.activeTab === 'myMaps') {
				return this.myMap.path ?? ''
			}
			return t('maps', 'Shows cool information')
		},
		backgroundImageUrl() {
			const iconColor = OCA.Accessibility?.theme === 'dark' ? 'ffffff' : '000000'
			if (this.activeTab === 'track') {
				return generateUrl('/svg/maps/road?color=' + iconColor)
			} else if (this.activeTab === 'favorite') {
				return generateUrl('/svg/core/actions/star?color=' + iconColor)
			} else if (this.activeTab === 'photo') {
				return this.previewUrl()
			} else if (this.activeTab === 'myMaps') {
				return generateFilePath('maps', 'img', 'maps.png')
			}
			return ''
		},
	}, */

	watch: {
	},
	methods: {
		/**
		 * Can this tab be displayed ?
		 *
		 * @param {object} tab a registered tab
		 * @return {boolean}
		 */
		canDisplay(tab) {
			return tab.enabled(this.fileInfo)
		},
		resetData() {
			this.error = null
			this.fileInfo = null
			this.$nextTick(() => {
				if (this.$refs.tabs) {
					this.$refs.tabs.updateTabs()
				}
			})
		},

		getPreviewIfAny(fileInfo) {
			if (fileInfo.hasPreview && !this.isFullScreen) {
				return OC.generateUrl(`/core/preview?fileId=${fileInfo.id}&x=${screen.width}&y=${screen.height}&a=true`)
			}
			return this.getIconUrl(fileInfo)
		},

		/**
		 * Copied from https://github.com/nextcloud/server/blob/16e0887ec63591113ee3f476e0c5129e20180cde/apps/files/js/filelist.js#L1377
		 * TODO: We also need this as a standalone library
		 *
		 * @param {object} fileInfo the fileinfo
		 * @return {string} Url to the icon for mimeType
		 */
		getIconUrl(fileInfo) {
			const mimeType = fileInfo.mimetype || 'application/octet-stream'
			if (mimeType === 'httpd/unix-directory') {
				// use default folder icon
				if (fileInfo.mountType === 'shared' || fileInfo.mountType === 'shared-root') {
					return OC.MimeType.getIconUrl('dir-shared')
				} else if (fileInfo.mountType === 'external-root') {
					return OC.MimeType.getIconUrl('dir-external')
				} else if (fileInfo.mountType !== undefined && fileInfo.mountType !== '') {
					return OC.MimeType.getIconUrl('dir-' + fileInfo.mountType)
				} else if (fileInfo.shareTypes && (
					fileInfo.shareTypes.indexOf(ShareTypes.SHARE_TYPE_LINK) > -1
					|| fileInfo.shareTypes.indexOf(ShareTypes.SHARE_TYPE_EMAIL) > -1)
				) {
					return OC.MimeType.getIconUrl('dir-public')
				} else if (fileInfo.shareTypes && fileInfo.shareTypes.length > 0) {
					return OC.MimeType.getIconUrl('dir-shared')
				}
				return OC.MimeType.getIconUrl('dir')
			}
			return OC.MimeType.getIconUrl(mimeType)
		},

		/**
		 * Set current active tab
		 *
		 * @param {string} id tab unique id
		 */
		setActiveTab(id) {
			OCA.Files.Sidebar.setActiveTab(id)
		},

		/**
		 * Toggle favourite state
		 * TODO: better implementation
		 *
		 * @param {boolean} state favourited or not
		 */
		async toggleStarred(state) {
			try {
				this.starLoading = true
				await axios({
					method: 'PROPPATCH',
					url: this.davPath,
					data: `<?xml version="1.0"?>
						<d:propertyupdate xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
						${state ? '<d:set>' : '<d:remove>'}
							<d:prop>
								<oc:favorite>1</oc:favorite>
							</d:prop>
						${state ? '</d:set>' : '</d:remove>'}
						</d:propertyupdate>`,
				})

				// TODO: Obliterate as soon as possible and use events with new files app
				// Terrible fallback for legacy files: toggle filelist as well
				if (OCA.Files && OCA.Files.App && OCA.Files.App.fileList && OCA.Files.App.fileList.fileActions) {
					OCA.Files.App.fileList.fileActions.triggerAction('Favorite', OCA.Files.App.fileList.getModelForFile(this.fileInfo.name), OCA.Files.App.fileList)
				}

			} catch (error) {
				OC.Notification.showTemporary(t('files', 'Unable to change the favourite state of the file'))
				console.error('Unable to change favourite state', error)
			}
			this.starLoading = false
		},

		onDefaultAction() {
			if (this.defaultAction) {
				// generate fake context
				this.defaultAction.action(this.fileInfo.name, {
					fileInfo: this.fileInfo,
					dir: this.fileInfo.dir,
					fileList: OCA.Files.App.fileList,
					// Fixme if defaultAction is needed
					$file: '',
				})
			}
		},

		/**
		 * Toggle the tags selector
		 */
		toggleTags() {
			if (OCA.SystemTags && OCA.SystemTags.View) {
				OCA.SystemTags.View.toggle()
			}
		},

		/**
		 * Open the sidebar for the given file
		 *
		 * @param {string} path the file path to load
		 * @param type
		 * @return {Promise}
		 * @throws {Error} loading failure
		 */
		async open(path, type = null) {
			// update current opened file
			this.Sidebar.file = path
			if (type) {
				this.typeOpened = type
			}

			if (path && path.trim() !== '') {
				// reset data, keep old fileInfo to not reload all tabs and just hide them
				this.error = null
				this.loading = true

				try {
					this.fileInfo = await FileInfo(this.davPath)
					// adding this as fallback because other apps expect it
					this.fileInfo.dir = this.file.split('/').slice(0, -1).join('/')

					// DEPRECATED legacy views
					// TODO: remove
					this.views.forEach(view => {
						view.setFileInfo(this.fileInfo)
					})

					this.$nextTick(() => {
						if (this.$refs.tabs) {
							this.$refs.tabs.updateTabs()
						}
					})
				} catch (error) {
					this.error = t('files', 'Error while loading the file data')
					console.error('Error while loading the file data', error)

					throw new Error(error)
				} finally {
					this.loading = false
				}
			} else {
				this.fileInfo = null
			}
		},

		/**
		 * Close the sidebar
		 */
		close() {
			this.Sidebar.file = ''
			this.resetData()
		},

		/**
		 * Allow to set the Sidebar as fullscreen from OCA.Files.Sidebar
		 *
		 * @param {boolean} isFullScreen - Wether or not to render the Sidebar in fullscreen.
		 */
		setFullScreenMode(isFullScreen) {
			this.isFullScreen = isFullScreen
		},

		/**
		 * Emit SideBar events.
		 */
		handleOpening() {
			emit('files:sidebar:opening')
		},
		handleOpened() {
			emit('files:sidebar:opened')
			this.$emit('opened')
		},
		handleClosing() {
			emit('files:sidebar:closing')
		},
		handleClosed() {
			emit('files:sidebar:closed')
		},
		onActiveChanged(newActive) {
			this.$emit('active-changed', newActive)
		},
		previewUrl() {
			return this.photo.hasPreview
				? generateUrl('core') + '/preview?fileId=' + this.photo.fileId + '&x=500&y=300&a=1'
				: generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
		},
		hasPreview() {
			return (this.activeTab === 'photo' && this.photo.hasPreview)
					|| (this.activeTab === 'myMaps' && this.myMap.hasPreview)
		},
	},
}
</script>

<style lang="scss" scoped>
::v-deep .icon-tab-track {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('../../img/road.svg') no-repeat;
	mask-size: 18px 18px;
	mask-position: center 7px;
	-webkit-mask: url('../../img/road.svg') no-repeat;
	-webkit-mask-size: 18px 18px;
	-webkit-mask-position: center top;
	min-width: 44px !important;
	min-height: 44px !important;
}

.app-sidebar {
	&--full {
		position: fixed !important;
		z-index: 2025 !important;
		top: 0 !important;
		height: 100% !important;
	}
	&--has-preview::v-deep {
		.app-sidebar-header__figure {
			background-size: cover;
		}

		&[data-mimetype="text/plain"],
		&[data-mimetype="text/markdown"] {
			.app-sidebar-header__figure {
				background-size: contain;
			}
		}
	}
}
</style>
