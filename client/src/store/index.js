import { configureStore } from '@reduxjs/toolkit'
import appReducer from './appSlice'

export const store = configureStore({
  reducer: {
    app: appReducer,
  },
  devTools: import.meta.env.DEV,
})
