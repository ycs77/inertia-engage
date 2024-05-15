import { createApp, h } from 'vue'
import { Link, createInertiaApp } from '@inertiajs/vue3'

createInertiaApp({
  resolve: async name => {
    const pages = import.meta.glob('./pages/**/*.vue')
    return await pages[`./pages/${name}.vue`]()
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .component('Link', Link)
      .mount(el)
  },
})
