import { boot } from 'quasar/wrappers'
import axios from 'axios'
import api from '../services/api'

export default boot(({ app }) => {
  app.config.globalProperties.$axios = axios
  app.config.globalProperties.$api = api
})

export { api }
