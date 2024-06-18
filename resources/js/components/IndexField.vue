<template>
  <span>{{ displayAs }}</span>
</template>

<script>
export default {
    props: ['resourceName', 'field'],

    computed: {
        fieldValue() {
            return this.field.displayedAs || this.field.value
        },

        displayAs() {
            return Object.entries(this.field.value).map(([key, value]) => `${value} (${key})`).join(', ');
        }
    }
}
</script>
