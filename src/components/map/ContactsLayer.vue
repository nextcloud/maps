<template>
	<template v-for="(c, i) in displayedContacts" :key="c.URI + i + c.ADR">
		<ContactLayer
			:contact="c"
			@address-deleted="$emit('address-deleted', $event)"
			@add-to-map-contact="$emit('add-to-map-contact', $event)" />
	</template>
</template>

<script>
import ContactLayer from './ContactLayer.vue'
import optionsController from '../../optionsController.js'
import { splitByNonEscapedComma } from '../../utils.js'

export default {
	name: 'ContactsLayer',
	components: {
		ContactLayer,
	},

	props: {
		contacts: {
			type: Array,
			required: true,
		},
		groups: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			optionValues: optionsController.optionValues,
		}
	},

	computed: {
		displayedContacts() {
			return this.contacts.filter((c) => {
				if (c.GROUPS) {
					try {
						const cGroups = splitByNonEscapedComma(c.GROUPS)
						for (let i = 0; i < cGroups.length; i++) {
							if (this.groups[cGroups[i]].enabled) {
								return true
							}
						}
					} catch (error) {
						console.error(error)
					}
				} else if (this.groups['0'].enabled) {
					return true
				}
				return false
			})
		},
	},
}
</script>
