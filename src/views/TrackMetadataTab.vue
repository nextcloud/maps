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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<TrackSidebarMetadataTab v-if="track"
		:track="track" />
</template>

<script setup>
import { ref } from 'vue'
import TrackSidebarMetadataTab from '../components/TrackMetadataTab.vue'
import * as network from '../network.js'
import { processGpx } from '../tracksUtils.js'
import { getToken } from '../utils/common.js'

const error = ref('')
const loading = ref(false)
const track = ref(null)

async function update(trackFileId) {
	getTrack(trackFileId)
}

function getTrack(trackFileId) {
	loading.value = true
	const t = {}
	network.getTrack(trackFileId, null, true, getToken()).then((response) => {
		if (!t.metadata) {
			try {
				t.metadata = JSON.parse(response.data.metadata)
			} catch (err) {
				console.error('Failed to parse track metadata')
			}
		}
		t.data = processGpx(response.data.content)
		track.value = t
	}).catch((err) => {
		console.error(err)
		error.value = err
	}).then(() => {
		t.loading = false
	})
}

function onScrollBottomReached() {}

function resetState() {
	error.value = ''
	loading.value = false
	track.value = null
}

defineExpose({ update, onScrollBottomReached, resetState })
</script>

<style lang="scss" scoped>
.comments {
	// Do not add emptycontent top margin
	&__error{
		margin-top: 0;
	}

	&__info {
		height: 60px;
		color: var(--color-text-maxcontrast);
		text-align: center;
		line-height: 60px;
	}
}
</style>
