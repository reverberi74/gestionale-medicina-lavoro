import axios from 'axios'

export const http = axios.create({
  baseURL: '/api',
  timeout: 15000,
  headers: {
    Accept: 'application/json',
  },
})

// Normalizzazione errori (utile per UI/Toast in futuro)
http.interceptors.response.use(
  (res) => res,
  (error) => {
    const msg =
      error?.response?.data?.error ||
      error?.response?.data?.message ||
      error?.message ||
      'Request failed'

    error.userMessage = msg
    return Promise.reject(error)
  }
)
