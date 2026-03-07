import { createApp } from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import TrackMetadataTab from './views/TrackMetadataTab.vue'

let appInstance = null
let TabInstance = null

function registerTab() {
	const fileList = window.OCA?.Files?.App?.fileList

	if (!fileList?.registerSidebarTab) {
		return false
	}

	fileList.registerSidebarTab({
		id: 'maps-track-metadata',
		name: t('maps', 'Metadata'),
		icon: 'icon-info',

		async mount(el, fileInfo, context) {
			// Unmount the previous instance if it exists
			if (appInstance) {
				appInstance.unmount()
			}

			// Create a new Vue 3 app instance for the sidebar tab
			appInstance = createApp(TrackMetadataTab)
			
			// Replace Vue.prototype with globalProperties
			appInstance.config.globalProperties.t = t
			appInstance.config.globalProperties.n = n

			// Mount it to the provided DOM element
			TabInstance = appInstance.mount(el)

			if (TabInstance && typeof TabInstance.update === 'function') {
				await TabInstance.update(fileInfo.id)
			}
		},

		update(fileInfo) {
			if (TabInstance && typeof TabInstance.update === 'function') {
				TabInstance.update(fileInfo.id)
			}
		},

		destroy() {
			if (appInstance) {
				appInstance.unmount()
				appInstance = null
				TabInstance = null
			}
		},

		enabled(fileInfo) {
			return fileInfo?.mimetype === 'application/gpx+xml'
		},

		scrollBottomReached() {
			if (TabInstance && typeof TabInstance.onScrollBottomReached === 'function') {
				TabInstance.onScrollBottomReached()
			}
		},
	})

	return true
}

document.addEventListener('DOMContentLoaded', () => {
	const interval = setInterval(() => {
		if (registerTab()) {
			clearInterval(interval)
		}
	}, 100)
})