import IndexField from './components/IndexField'
import DetailField from './components/DetailField'
import FormField from './components/FormField'

Nova.booting((app, store) => {
    app.component('index-translatable-field', IndexField)
    app.component('detail-translatable-field', DetailField)
    app.component('form-translatable-field', FormField)
})
