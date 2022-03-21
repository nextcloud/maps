<template>
	<div v-if="photo"
		id="photo-tab">
	</div>
</template>

<script>

import { generateUrl } from '@nextcloud/router'

export default {
	name: 'PhotoSidebarTab',

	components: {
	},

	props: {
		photo: {
			required: true,
			validator: prop => typeof prop === 'object' || prop === null,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		previewUrl() {
			return this.photo.hasPreview
				? generateUrl('core') + '/preview?fileId=' + this.photo.fileId + '&x=500&y=300&a=1'
				: generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
		},
		title() {
			return this.photo.basename
		},
		filename() {
			return this.photo.filename
		},
		path() {
			return this.photo.path
		},
		size() {
			return this.photo.size
		},
	},

	watch: {
	},

	methods: {
	},
}
</script>

<style lang="scss" scoped>
#photo-tab {
	padding: 0 10px 0 10px;
	background-image: "previewUrl";
}
</style>
