<template>
	<NcAppSidebar v-show="show"
		v-bind="appSidebar"
		@update:active="onActiveChanged"
		@update:starred="toggleStarred"
		@[defaultActionListener].stop.prevent="onDefaultAction"
		@opening="handleOpening"
		@opened="handleOpened"
		@closing="handleClosing"
		@closed="handleClosed"
		@close="$emit('close')">
		<template #header>
			<span v-if="icon" :class="['header-icon icon', icon]" />
		</template>
		<FavoriteSidebarTab v-if="activeTab === 'favorite'"
			:favorite="favorite"
			:categories="favoriteCategories"
			@edit="$emit('edit-favorite', $event)"
			@delete="$emit('delete-favorite', $event)" />
		<PhotoSuggestionsSidebarTab v-if="activeTab === 'photo-suggestion' && !fileInfo"
			:photo-suggestions="photoSuggestions"
			:photo-suggestions-tracks-and-devices="photoSuggestionsTracksAndDevices"
			:photo-suggestions-selected-indices="photoSuggestionsSelectedIndices"
			:photo-suggestions-hide-photos="photoSuggestionsHidePhotos"
			:photo-suggestions-timezone="photoSuggestionsTimezone"
			:loading="photosLoading"
			@load-more="$emit('load-more-photo-suggestions')"
			@change-timezone="$emit('change-photo-suggestions-timezone',$event)"
			@select-all="$emit('select-all-photo-suggestions')"
			@clear-selection="$emit('clear-photo-suggestions-selection',$event)"
			@cancel="$emit('cancel-photo-suggestions')"
			@toggle-hide-photos="$emit('toggle-photo-suggestions-hide-photo')"
			@save="$emit('save-photo-suggestions-selection',$event)"
			@zoom="$emit('zoom-photo-suggestion', $event)"
			@toggle-track-or-device="$emit('photo-suggestion-toggle-track-or-device', $event)" />
		<TrackMetadataTab v-if="isPublic() && activeTab === 'maps-track-metadata' && !fileInfo"
			:track="track" />
		<!-- TODO: create a standard to allow multiple elements here? -->
		<template v-if="fileInfo" #description>
			<LegacyView v-for="view in views"
				:key="view.cid"
				:component="view"
				:file-info="fileInfo" />
		</template>

		<!-- NcActions menu -->
		<template v-if="fileInfo" #secondary-actions>
			<!-- TODO: create proper api for apps to register actions
			And inject themselves here. -->
			<NcActionButton v-if="isSystemTagsEnabled"
				:close-after-click="true"
				icon="icon-tag"
				@click="toggleTags">
				{{ t('files', 'Tags') }}
			</NcActionButton>
		</template>

		<!-- Error display -->
		<NcEmptyContent v-if="error" icon="icon-error">
			{{ error }}
		</NcEmptyContent>

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
				:file-info="fileInfo">
				<template v-if="tab.iconSvg !== undefined" #icon>
					<!-- eslint-disable-next-line vue/no-v-html -->
					<span class="svg-icon" v-html="tab.iconSvg" />
				</template>
			</SidebarTab>
		</template>
	</NcAppSidebar>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { t } from '@nextcloud/l10n'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import { emit } from '@nextcloud/event-bus'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { encodePath } from '@nextcloud/paths'
import moment from '@nextcloud/moment'
import { Type as ShareTypes } from '@nextcloud/sharing'
import axios from '@nextcloud/axios'

import FavoriteSidebarTab from '../components/FavoriteSidebarTab.vue'
import PhotoSuggestionsSidebarTab from './Sidebar/PhotoSuggestionsSidebarTab.vue'
import SidebarTab from './Sidebar/SidebarTab.vue'
import LegacyView from './Sidebar/LegacyView.vue'
import TrackMetadataTab from './TrackMetadataTab.vue'
import FileInfo from '../services/FileInfo.js'
import { isPublic } from '../utils/common.js'
import { sidebarState, setActiveSidebarTab } from '../sidebarState.js'

const props = defineProps({
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
	photosLoading: {
		required: true,
		type: Boolean,
	},
	photoSuggestions: {
		required: true,
		type: Array,
	},
	photoSuggestionsTracksAndDevices: {
		required: true,
		type: Object,
	},
	photoSuggestionsSelectedIndices: {
		required: true,
		type: Array,
	},
	photoSuggestionsHidePhotos: {
		type: Boolean,
		default: false,
	},
	photoSuggestionsTimezone: {
		required: true,
		type: String,
	},
	track: {
		required: false,
		validator: prop => typeof prop === 'object' || prop === null,
		default: null,
	},
})

const emit2 = defineEmits(['close', 'edit-favorite', 'delete-favorite', 'load-more-photo-suggestions',
	'change-photo-suggestions-timezone', 'select-all-photo-suggestions', 'clear-photo-suggestions-selection',
	'cancel-photo-suggestions', 'toggle-photo-suggestions-hide-photo', 'save-photo-suggestions-selection',
	'zoom-photo-suggestion', 'photo-suggestion-toggle-track-or-device', 'active-changed', 'opened'])

const fileInfo = ref(null)
const error = ref(false)
const loading = ref(false)
const isFullScreen = ref(false)
const typeOpened = ref('')
const name = ref(null)
const icon = ref(null)
const starLoading = ref(false)

const tabs = ref([])
const views = ref([])

const file = computed(() => sidebarState.file)
const activeTab = computed(() => sidebarState.activeTab)

const davPath = computed(() => {
	const user = OC.getCurrentUser().uid
	return OC.linkToRemote(`dav/files/${user}${encodePath(file.value)}`)
})

const subname = computed(() => `${size.value}, ${moment(fileInfo.value.mtime).fromNow()}`)
const fullTime = computed(() => moment(fileInfo.value.mtime).format('LLL'))
const size = computed(() => OC.Util.humanFileSize(fileInfo.value.size))

const defaultAction = computed(() =>
	fileInfo.value
		&& OCA.Files && OCA.Files.App && OCA.Files.App.fileList
		&& OCA.Files.App.fileList.fileActions
		&& OCA.Files.App.fileList.fileActions.getDefaultFileAction
		&& OCA.Files.App.fileList.fileActions.getDefaultFileAction(fileInfo.value.mimetype, fileInfo.value.type, OC.PERMISSION_READ),
)

const defaultActionListener = computed(() => defaultAction.value ? 'figure-click' : null)
const isSystemTagsEnabled = computed(() => OCA && 'SystemTags' in OCA)

const appSidebar = computed(() => {
	icon.value = null
	if (fileInfo.value) {
		return {
			'data-mimetype': fileInfo.value.mimetype,
			'star-loading': starLoading.value,
			active: activeTab.value,
			class: {
				'app-sidebar--has-preview': fileInfo.value.hasPreview && !isFullScreen.value,
				'app-sidebar--full': isFullScreen.value,
			},
			compact: !fileInfo.value.hasPreview || isFullScreen.value,
			loading: loading.value,
			starred: fileInfo.value.isFavourited,
			subname: subname.value,
			subnameTooltip: fullTime.value,
			name: name.value ?? fileInfo.value.name,
			nameTooltip: fileInfo.value.name,
		}
	} else if (error.value) {
		return { key: 'error', subname: '', name: '' }
	} else if (loading.value) {
		return { loading: loading.value, subname: '', name: '' }
	} else if (activeTab.value === 'favorite') {
		icon.value = 'icon-favorite'
		return {
			name: t('maps', 'Favorite'),
			compact: true,
			subname: '',
			active: activeTab.value,
			class: { 'app-sidebar--has-preview': false, 'app-sidebar--full': isFullScreen.value },
		}
	} else if (activeTab.value === 'photo-suggestion') {
		icon.value = 'icon-picture'
		return {
			name: t('maps', 'Photo suggestions'),
			compact: true,
			subname: '',
			active: activeTab.value,
			class: { 'app-sidebar--has-preview': false, 'app-sidebar--full': isFullScreen.value },
		}
	} else if (activeTab.value === 'maps-track-metadata') {
		return {
			name: t('maps', 'Track metadata'),
			compact: true,
			subname: '',
			active: activeTab.value,
			class: { 'app-sidebar--has-preview': false, 'app-sidebar--full': isFullScreen.value },
		}
	} else {
		return { loading: false, subname: '', name: '' }
	}
})

function resetData() {
	error.value = null
	fileInfo.value = null
}

function getPreviewIfAny(fi) {
	if (fi.hasPreview && !isFullScreen.value) {
		return OC.generateUrl(`/core/preview?fileId=${fi.id}&x=${screen.width}&y=${screen.height}&a=true`)
	}
	return getIconUrl(fi)
}

function getIconUrl(fi) {
	const mimeType = fi.mimetype || 'application/octet-stream'
	if (mimeType === 'httpd/unix-directory') {
		if (fi.mountType === 'shared' || fi.mountType === 'shared-root') {
			return OC.MimeType.getIconUrl('dir-shared')
		} else if (fi.mountType === 'external-root') {
			return OC.MimeType.getIconUrl('dir-external')
		} else if (fi.mountType !== undefined && fi.mountType !== '') {
			return OC.MimeType.getIconUrl('dir-' + fi.mountType)
		} else if (fi.shareTypes && (
			fi.shareTypes.indexOf(ShareTypes.SHARE_TYPE_LINK) > -1
			|| fi.shareTypes.indexOf(ShareTypes.SHARE_TYPE_EMAIL) > -1)
		) {
			return OC.MimeType.getIconUrl('dir-public')
		} else if (fi.shareTypes && fi.shareTypes.length > 0) {
			return OC.MimeType.getIconUrl('dir-shared')
		}
		return OC.MimeType.getIconUrl('dir')
	}
	return OC.MimeType.getIconUrl(mimeType)
}

function setActiveTab(id) {
	setActiveSidebarTab(id)
}

async function toggleStarred(state) {
	try {
		starLoading.value = true
		await axios({
			method: 'PROPPATCH',
			url: davPath.value,
			data: `<?xml version="1.0"?>
				<d:propertyupdate xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
				${state ? '<d:set>' : '<d:remove>'}
					<d:prop>
						<oc:favorite>1</oc:favorite>
					</d:prop>
				${state ? '</d:set>' : '</d:remove>'}
				</d:propertyupdate>`,
		})
		if (OCA.Files && OCA.Files.App && OCA.Files.App.fileList && OCA.Files.App.fileList.fileActions) {
			OCA.Files.App.fileList.fileActions.triggerAction('Favorite', OCA.Files.App.fileList.getModelForFile(fileInfo.value.name), OCA.Files.App.fileList)
		}
	} catch (err) {
		showError(t('files', 'Unable to change the favourite state of the file'))
		console.error('Unable to change favourite state', err)
	}
	starLoading.value = false
}

function onDefaultAction() {
	if (defaultAction.value) {
		defaultAction.value.action(fileInfo.value.name, {
			fileInfo: fileInfo.value,
			dir: fileInfo.value.dir,
			fileList: OCA.Files.App.fileList,
			$file: '',
		})
	}
}

function toggleTags() {
	if (OCA.SystemTags && OCA.SystemTags.View) {
		OCA.SystemTags.View.toggle()
	}
}

async function open(path = null, type = null, openName = null) {
	sidebarState.file = path
	if (path) {
		typeOpened.value = type
		name.value = openName
	}

	if (path && path.trim() !== '' && !isPublic()) {
		error.value = null
		loading.value = true
		try {
			fileInfo.value = await FileInfo(davPath.value)
			fileInfo.value.dir = file.value.split('/').slice(0, -1).join('/')
		} catch (err) {
			error.value = t('files', 'Error while loading the file data')
			console.error('Error while loading the file data', err)
			throw new Error(err)
		} finally {
			loading.value = false
		}
	} else {
		fileInfo.value = null
	}
}

function close() {
	sidebarState.file = ''
	resetData()
}

function setFullScreenMode(val) {
	isFullScreen.value = val
}

function handleOpening() { emit('files:sidebar:opening') }
function handleOpened() { emit('files:sidebar:opened'); emit2('opened') }
function handleClosing() { emit('files:sidebar:closing') }
function handleClosed() { emit('files:sidebar:closed') }
function onActiveChanged(newActive) { emit2('active-changed', newActive) }

defineExpose({ open, close, setActiveTab, setFullScreenMode })
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

	&--full {
		position: fixed !important;
		z-index: 2025 !important;
		top: 0 !important;
		height: 100% !important;
	}

	.svg-icon {
		::v-deep svg {
			width: 20px;
			height: 20px;
			fill: currentColor;
		}
	}
}

.header-icon {
	display: block;
	width: 70px;
	height: 60px;
	background-size: 40px 40px;
}
</style>
