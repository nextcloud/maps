<template>
	<LControl position="topleft" class="maps-search-control leaflet-control" :class="{'mobile':isMobile, 'desktop': !isMobile}">
		<div id="search">
			<SearchField
				class="search-field"
				:data="searchData"
				:loading="loading"
				@validate="$emit('validate', $event)" />
			<button
				v-tooltip="{ content: t('maps', 'Find directions') }"
				class="bar-button"
				@click="$emit('routing-clicked')">
				<span class="icon icon-routing" />
			</button>
			<button v-if="resultPoiNumber > 0"
				v-tooltip="{ content: t('maps', 'Clear POIs') }"
				class="bar-button"
				@click="$emit('clear-pois')">
				<span class="icon icon-close" />
			</button>
		</div>
	</LControl>
</template>

<script>
import { getLocale } from '@nextcloud/l10n'
import { LControl } from 'vue2-leaflet'
import { isMobile } from '@nextcloud/vue'

import SearchField from './SearchField.vue'

export default {
	name: 'SearchControl',

	components: {
		LControl,
		SearchField,
	},

	mixins: [isMobile],

	props: {
		map: {
			type: Object,
			required: true,
		},
		searchData: {
			type: Array,
			required: true,
		},
		loading: {
			type: Boolean,
			default: false,
		},
		resultPoiNumber: {
			type: Number,
			default: 0,
		},
	},

	data() {
		return {
			locale: getLocale(),
		}
	},

	watch: {
	},

	created() {
	},

	methods: {
	},
}
</script>

<style lang="scss" scoped>
.desktop {
	width: 350px;
	margin-left: 56px !important;
}
.mobile {
	width: 100vw;
	background: var(--color-main-background);
	padding-left: 56px;
	padding-right: 56px;
	padding-top: 10px;
	padding-bottom: 7px;
	margin-top: 0px !important;
}
.maps-search-control {
	z-index: 99999999 !important;
	#search {
		display: flex;
		> button {
			min-width: 34px;
			width: 34px;
			height: 34px;
			// margin: 0;
			padding: 0;
		}
		.multiselect {
			flex-grow: 1;
			min-width: 100px;
			background: transparent;
		}
	}

	.bar-button {
		margin: 5px;
		padding: 0px 0px 2px;

		.icon-routing {
			margin-right: 2px;
		}
		.icon {
			opacity: 0.5;
		}
		&:hover .icon {
			opacity: 1;
		}
	}
}

::v-deep .icon-dot-circle {
	background-color: var(--color-main-text);
	mask: url('../../../img/dot-circle.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../../img/dot-circle.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}
</style>
