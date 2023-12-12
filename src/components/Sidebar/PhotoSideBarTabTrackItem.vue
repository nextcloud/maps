<template>
	<NcAppNavigationItem
		:icon="'icon-road'"
		:class="{ 'item-disabled': !enabled }"
		:name="track.name"
		:allow-collapse="subTracks.length > 1"
		:open="open"
		:force-menu="false"
		@click="onTrackClick">
		<NcCounterBubble slot="counter">
			{{ track.suggestionCount > 99 ? '99+' : track.suggestionCount }}
		</NcCounterBubble>
		<template v-if="subTracks.length && subTracks.length > 1" slot="default">
			<b v-show="false">dummy</b>
			<NcAppNavigationItem
				v-for="st in subTracks"
				:key="st.key"
				:class="{ 'item-disabled': !st.enabled }"
				:name="track.name.concat(' ', st.key.split(':')[2])"
				:force-menu="false"
				@click="$emit('subtrack-click', st)">
				<NcCounterBubble slot="counter">
					{{ st.suggestionCount > 99 ? '99+' : st.suggestionCount }}
				</NcCounterBubble>
			</NcAppNavigationItem>
		</template>
	</NcAppNavigationItem>
</template>

<script>

import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'

export default {
	name: 'PhotoSideBarTabTrackItem',

	components: {
		NcAppNavigationItem,
		NcCounterBubble,
	},

	props: {
		track: {
			required: true,
			type: Object,
		},
		subTracks: {
			required: false,
			type: Array,
			default() { return [] },
		},
	},

	data() {
		return {
			open: !!this.track.open,
			enabled: this.subTracks.some((t) => { return t.enabled }),
		}
	},

	computed: {
	},

	methods: {
		onTrackClick() {
			this.open = !this.open
			if (this.subTracks.length < 2) {
				this.enabled = !this.enabled
				this.$emit('subtrack-click', this.subTracks[0])
			}
		},
	},
}
</script>

<style scoped>

::v-deep .item-disabled {
	opacity: 0.5;
}

::v-deep .icon-road {
	background-color: var(--color-main-text);
	mask: url('../../../img/road.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../../img/road.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}

</style>
