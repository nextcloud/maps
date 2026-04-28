<template>
	<NcAppNavigationItem
		:icon="'icon-road'"
		:class="{ 'item-disabled': !enabled }"
		:name="track.name"
		:allow-collapse="subTracks.length > 1"
		:open="open"
		:force-menu="false"
		@click="onTrackClick">
		<template #counter>
			<NcCounterBubble :count="track.suggestionCount" />
		</template>
		<template v-if="subTracks.length && subTracks.length > 1" #default>
			<b v-show="false">dummy</b>
			<NcAppNavigationItem
				v-for="st in subTracks"
				:key="st.key"
				:class="{ 'item-disabled': !st.enabled }"
				:name="track.name.concat(' ', st.key.split(':')[2])"
				:force-menu="false"
				@click="$emit('subtrack-click', st)">
				<template #counter>
					<NcCounterBubble :count="st.suggestionCount" />
				</template>
			</NcAppNavigationItem>
		</template>
	</NcAppNavigationItem>
</template>

<script setup>
import { ref } from 'vue'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'

const props = defineProps({
	track: {
		required: true,
		type: Object,
	},
	subTracks: {
		required: false,
		type: Array,
		default: () => [],
	},
})

const emit = defineEmits(['subtrack-click'])

const open = ref(!!props.track.open)
const enabled = ref(props.subTracks.some((t) => t.enabled))

function onTrackClick() {
	open.value = !open.value
	if (props.subTracks.length < 2) {
		enabled.value = !enabled.value
		emit('subtrack-click', props.subTracks[0])
	}
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
