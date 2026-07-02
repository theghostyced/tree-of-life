<script lang="ts">
    import { onMount } from 'svelte';

    /**
     * Animated backdrop for the authentication surfaces, rendered with
     * Paper Shaders (@paper-design/shaders). A slow, dark sage mesh gradient
     * that stays subtle behind the login card.
     *
     * The shader is imported dynamically inside onMount so it never touches
     * the server during Inertia SSR, and it pauses when the user prefers
     * reduced motion.
     */
    let container: HTMLDivElement;

    onMount(() => {
        let disposed = false;
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        let shader: { dispose: () => void } | undefined;

        const prefersReducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        import('@paper-design/shaders').then(
            ({
                ShaderMount,
                meshGradientFragmentShader,
                getShaderColorFromString,
            }) => {
                if (disposed) return;

                shader = new ShaderMount(
                    container,
                    meshGradientFragmentShader,
                    {
                        u_colors: [
                            getShaderColorFromString('#0A0C0B'),
                            getShaderColorFromString('#0B0E0C'),
                            getShaderColorFromString('#0E120F'),
                            getShaderColorFromString('#141A15'),
                        ],
                        u_colorsCount: 4,
                        u_distortion: 0.3,
                        u_swirl: 0.05,
                        u_grainMixer: 0,
                        u_grainOverlay: 0,
                        u_fit: 2, // cover
                        u_scale: 1.4,
                        u_rotation: 0,
                        u_offsetX: 0,
                        u_offsetY: 0,
                        u_originX: 0.5,
                        u_originY: 0.5,
                        u_worldWidth: 0,
                        u_worldHeight: 0,
                    },
                    undefined,
                    prefersReducedMotion ? 0 : 0.1,
                    0,
                );
            },
        );

        return () => {
            disposed = true;
            shader?.dispose();
        };
    });
</script>

<div
    bind:this={container}
    class="pointer-events-none absolute inset-0"
    aria-hidden="true"
></div>
