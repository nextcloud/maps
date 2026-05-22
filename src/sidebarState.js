/**
 * Reactive state for the Maps internal sidebar.
 * Replaces the former OCA.Files.Sidebar.state dependency.
 */
import { reactive } from 'vue'

export const sidebarState = reactive({
	/** Current file path open in the sidebar, or empty string when closed */
	file: '',
	/** ID of the currently active sidebar tab */
	activeTab: '',
})

export function setActiveSidebarTab(id) {
	sidebarState.activeTab = id
}
