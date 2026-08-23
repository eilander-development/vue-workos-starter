import react from '@vitejs/plugin-react';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { existsSync, readFileSync } from 'fs';
import { resolve } from 'path';
import { defineConfig, loadEnv, type ServerOptions } from 'vite';

function resolveDevServerConfig(env: Record<string, string>): {
    port: number;
    origin: string;
    https: ServerOptions['https'];
    hmr: NonNullable<ServerOptions['hmr']>;
} {
    const appUrl = env.APP_URL ? new URL(env.APP_URL) : null;
    const certRoots = [
        env.SAIL_MANAGER_CERTS_PATH,
        '/sail-manager/certs',
        resolve(process.cwd(), '../../sail/infrastructure/certs'),
    ].filter((value): value is string => Boolean(value));

    const certRoot = certRoots.find((root) => existsSync(resolve(root, 'localhost.crt')) && existsSync(resolve(root, 'localhost.key')));
    const certFile = certRoot ? resolve(certRoot, 'localhost.crt') : '';
    const keyFile = certRoot ? resolve(certRoot, 'localhost.key') : '';
    const port = Number.parseInt(env.VITE_SERVER_PORT || env.VITE_PORT || '5173', 10);
    const serverHost = appUrl?.hostname || env.VITE_SERVER_IP || 'localhost';
    const useHttps = appUrl?.protocol === 'https:' && certFile !== '' && keyFile !== '';

    return {
        port,
        origin: `${useHttps ? 'https' : 'http'}://${serverHost}:${port}`,
        https: useHttps
            ? {
                  cert: readFileSync(certFile),
                  key: readFileSync(keyFile),
              }
            : undefined,
        hmr: {
            host: serverHost,
            protocol: useHttps ? 'wss' : 'ws',
            port,
        },
    };
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const devServer = resolveDevServerConfig(env);

    return {
        plugins: [
            laravel({
                input: ['resources/js/app.ts', 'resources/js/sparen/main.tsx'],
                ssr: 'resources/js/ssr.ts',
                refresh: true,
            }),
            tailwindcss(),
            wayfinder({
                formVariants: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            react(),
        ],
        server: {
            watch: {
                usePolling: true,
            },
            host: '0.0.0.0',
            port: devServer.port,
            strictPort: true,
            origin: devServer.origin,
            https: devServer.https,
            hmr: devServer.hmr,
        },
    };
});
