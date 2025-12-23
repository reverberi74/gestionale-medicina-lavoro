import { createSlice } from '@reduxjs/toolkit'

const appSlice = createSlice({
  name: 'app',
  initialState: {
    bootedAt: new Date().toISOString(),
  },
  reducers: {},
})

export default appSlice.reducer
