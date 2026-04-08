export default defineNuxtPlugin((nuxtApp) => {
  const { initializeLocations } = useLocations()

  nuxtApp.hook('app:mounted', async () => {
    await initializeLocations()
  })
})
