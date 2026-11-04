import { defineConfig } from "kirbyup/config";

export default defineConfig({
  vite: {
    server: {
      host: "127.0.0.1",
      hmr: {
        host: "127.0.0.1",
        clientPort: 5177,
      },
    },
  },
});
