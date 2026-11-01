// hls.js ships the light build without its own types: they are the full build's
declare module 'hls.js/light' {
  export * from 'hls.js'
  export { default } from 'hls.js'
}
