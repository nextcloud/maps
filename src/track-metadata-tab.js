// TODO : check implem
import Vue from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import TrackMetadataTab from './views/TrackMetadataTab.vue'

Vue.prototype.t = t
Vue.prototype.n = n

const View = Vue.extend(TrackMetadataTab)

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
			if (TabInstance) {
				TabInstance.$destroy()
			}

			TabInstance = new View({
				parent: context,
			})

			await TabInstance.update(fileInfo.id)
			TabInstance.$mount(el)
		},

		update(fileInfo) {
			if (TabInstance) {
				TabInstance.update(fileInfo.id)
			}
		},

		destroy() {
			if (TabInstance) {
				TabInstance.$destroy()
				TabInstance = null
			}
		},

		enabled(fileInfo) {
			return fileInfo?.mimetype === 'application/gpx+xml'
		},

		scrollBottomReached() {
			if (TabInstance?.onScrollBottomReached) {
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