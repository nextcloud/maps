<template>
	<LControl class="maps-search-control" position="topleft">
		<div id="search">
			<SearchField
				class="search-field"
				:data="searchData"
				@validate="$emit('validate', $event)" />
			<button
				v-tooltip="{ content: t('maps', 'Find directions') }"
				class="routing-button"
				@click="$emit('routing-clicked')">
				<span class="icon-routing" />
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

	.routing-button {
		margin: 0 0 0 5px;
		.icon-routing {
			opacity: 0.5;
		}
		&:hover .icon-routing {
			opacity: 1;
		}
	}
}
</style>
