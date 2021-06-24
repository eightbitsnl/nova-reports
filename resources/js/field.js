Nova.booting((Vue, router, store) => {
	Vue.component('index-querybuilder-field', require('./components/IndexField'))
	Vue.component('detail-querybuilder-field', require('./components/DetailField'))
	Vue.component('form-querybuilder-field', require('./components/FormField'))
  })
  