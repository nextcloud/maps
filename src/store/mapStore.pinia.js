import { defineStore } from 'pinia'
import { ref } from 'vue'
import MapMode from '../data/enum/MapMode.js'

export const useMapStore = defineStore('map', () => {
	const mode = ref(MapMode.DEFAULT)

	function setMode(newMode) {
		mode.value = newMode
	}

	return { mode, setMode }
})
