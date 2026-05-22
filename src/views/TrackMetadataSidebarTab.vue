<!--
  - @copyright Copyright (c) 2022 Arne Hamann <git@arne.email>
  -
  - @author Arne Hamann <git@arne.email>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->
<script setup>
import { ref, watch } from 'vue'
import TrackSidebarMetadataTab from '../components/TrackMetadataTab.vue'
import * as network from '../network.js'
import { processGpx } from '../tracksUtils.js'
import { getToken } from '../utils/common.js'

const props = defineProps({
	node: {
		type: Object,
		default: null,
	},
	active: {
		type: Boolean,
		default: false,
	},
})

const track = ref(null)
const loading = ref(false)

async function loadTrack(fileId) {
	if (!fileId) return
	loading.value = true
	track.value = null
	try {
		const response = await network.getTrack(fileId, null, true, getToken())
		const t = {}
		try {
			t.metadata = JSON.parse(response.data.metadata)
		} catch (e) {
			console.error('Failed to parse track metadata')
		}
		t.data = processGpx(response.data.content)
		track.value = t
	} catch (error) {
		console.error('Error loading track metadata', error)
	} finally {
		loading.value = false
	}
}

watch(() => props.node?.fileid, (fileId) => {
	loadTrack(fileId)
}, { immediate: true })
</script>

<template>
	<TrackSidebarMetadataTab v-if="track" :track="track" />
</template>
