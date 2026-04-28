<template>
	<template v-for="(contact, i) in displayedContacts" :key="contact.URI + i + contact.ADR">
		<ContactLayer
			:contact="contact"
			@addressDeleted="$emit('addressDeleted', $event)"
			@addToMapContact="$emit('addToMapContact', $event)" />
	</template>
</template>

<script setup>
import { computed } from 'vue'
import ContactLayer from './ContactLayer.vue'
import { splitByNonEscapedComma } from '../../utils.js'

const props = defineProps({
	contacts: {
		type: Array,
		required: true,
	},
	groups: {
		type: Object,
		required: true,
	},
})

defineEmits(['addressDeleted', 'addToMapContact'])

const displayedContacts = computed(() =>
	props.contacts.filter((c) => {
		if (c.GROUPS) {
			try {
				const cGroups = splitByNonEscapedComma(c.GROUPS)
				for (let i = 0; i < cGroups.length; i++) {
					if (props.groups[cGroups[i]].enabled) return true
				}
			} catch (error) {
				console.error(error)
			}
		} else if (props.groups['0'].enabled) {
			return true
		}
		return false
	}),
)
</script>
