<!--
  - @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
	<NcAppSidebarTab :id="id"
		ref="tab"
		:name="name"
		:icon="icon"
		@bottomReached="onScrollBottomReached">
		<template #icon>
			<slot name="icon" />
		</template>
		<!-- Fallback loading -->
		<NcEmptyContent v-if="loading" icon="icon-loading" />

		<!-- Using a dummy div as Vue mount replace the element directly
			It does NOT append to the content -->
		<div ref="mount" />
	</NcAppSidebarTab>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

const props = defineProps({
	fileInfo: {
		type: Object,
		default: () => {},
		required: true,
	},
	id: {
		type: String,
		required: true,
	},
	name: {
		type: String,
		required: true,
	},
	icon: {
		type: String,
		required: false,
		default: undefined,
	},
	onMount: {
		type: Function,
		required: true,
	},
	onUpdate: {
		type: Function,
		required: true,
	},
	onDestroy: {
		type: Function,
		required: true,
	},
	onScrollBottomReached: {
		type: Function,
		default: () => {},
	},
})

const tab = ref(null)
const mount = ref(null)
const loading = ref(true)

watch(() => props.fileInfo, async (newFile, oldFile) => {
	if (newFile.id !== oldFile.id) {
		loading.value = true
		await props.onUpdate(props.fileInfo)
		loading.value = false
	}
})

onMounted(async () => {
	loading.value = true
	await props.onMount(mount.value, props.fileInfo, tab.value)
	loading.value = false
})

onBeforeUnmount(async () => {
	await props.onDestroy()
})
</script>
