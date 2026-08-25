import { createApp } from 'vue'

import App from '@/App.vue'
import router from '@/router'
import { pinia } from '@/plugins/pinia'
import { setUnauthorizedHandler } from '@/services/api'
import { useAuthStore } from '@/modules/auth/stores/auth.store'

import '@/assets/styles/main.css'

const app = createApp(App)

app.use(pinia)
app.use(router)

const authStore = useAuthStore(pinia)

setUnauthorizedHandler(() => {
  authStore.clearSession()

  if (router.currentRoute.value.name !== 'login') {
    void router.replace({
      name: 'login',
      query: {
        reason: 'session-expired',
      },
    })
  }
})

app.mount('#app')