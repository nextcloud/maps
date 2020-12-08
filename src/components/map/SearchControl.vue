<template>
	<LControl class="maps-search-control" position="topleft">
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
// import { generateUrl } from '@nextcloud/router'
// import moment from '@nextcloud/moment'

import { LControl } from 'vue2-leaflet'

// import * as network from '../../network'
// import optionsController from '../../optionsController'
import SearchField from './SearchField.vue'

export default {
	name: 'SearchControl',

	components: {
		LControl,
		SearchField,
	},

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
.maps-search-control {
	z-index: 99999999 !important;
	width: 350px;

	#search {
		display: flex;
		> button {
			width: 34px;
			height: 34px;
			// margin: 0;
			padding: 0;
		}
		.multiselect {
			flex-grow: 1;
			background: transparent;
		}
	}

	.bar-button {
		margin: 0 0 0 5px;
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
