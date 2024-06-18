<template>
    <div>
        Hi
        <div v-for="locale in locales" :key="locale">
            <label :for="'value-' + locale">{{ locale.toUpperCase() }}</label>
            <textarea :id="'value-' + locale" v-model="values[locale]" class="w-full form-control"></textarea>
        </div>
    </div>
</template>

<script>
export default {
    props: ['resourceName', 'resourceId', 'field'],
    data() {
        return {
            values: {}
        };
    },
    computed: {
        locales() {
            return this.field.locales;
        },
        key() {
            return this.field.key;
        }
    },
    mounted() {
        // Load existing localizations if available
        if (this.field.value) {
            this.values = this.field.value;
        }
    }
}
</script>
