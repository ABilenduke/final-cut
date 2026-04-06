export default defineNuxtPlugin(() => {
  // Ensure toast composable state is initialized on client.
  // The actual toast rendering is handled by CvToast in the default layout.
  // useToast() composable will be implemented in Plan 05.
})
