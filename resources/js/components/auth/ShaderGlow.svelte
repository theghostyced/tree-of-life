<script lang="ts">
    import { onMount } from 'svelte';

    /**
     * WebGL glow field for the auth brand panel. Renders the dark panel base with
     * a few soft radial glows (leaf + warm amber) whose centres drift very slowly
     * via `u_time`, as one continuous field. Uses highp precision + a dither to
     * avoid 8-bit banding on the subtle dark gradient, and renders at native
     * device resolution. Honours `prefers-reduced-motion` (static frame). Falls
     * back to the panel's own background if WebGL is unavailable.
     */
    let canvas: HTMLCanvasElement;

    const vert = `
        attribute vec2 p;
        void main() { gl_Position = vec4(p, 0.0, 1.0); }
    `;

    const frag = `
        #ifdef GL_FRAGMENT_PRECISION_HIGH
        precision highp float;
        #else
        precision mediump float;
        #endif

        uniform vec2 u_res;
        uniform float u_time;

        // Soft round glow: 1.0 at the centre, easing to 0 by radius r.
        float glow(vec2 uv, vec2 c, float r, float aspect) {
            vec2 d = uv - c;
            d.x *= aspect;               // keep circles round on a tall panel
            return smoothstep(r, 0.0, length(d));
        }

        // Cheap hash for dithering, to break up 8-bit gradient banding.
        float hash(vec2 p) {
            return fract(sin(dot(p, vec2(12.9898, 78.233))) * 43758.5453);
        }

        void main() {
            vec2 uv = gl_FragCoord.xy / u_res;
            float aspect = u_res.x / u_res.y;
            float t = u_time;

            vec3 base  = vec3(0.082, 0.502, 0.239); // --color-accent #15803d
            // Lighter than the base so the glows read as light falling on it.
            vec3 leaf  = vec3(0.133, 0.773, 0.369); // --color-accent-strong #16a34a
            vec3 amber = vec3(0.918, 0.345, 0.047); // --color-glow-amber #ea580c

            // Slowly drifting centres (small amplitudes, low frequencies).
            // Ocean-like flow: layered traveling waves warp the sampling space,
            // so the whole glow field undulates and drifts like slow water.
            vec2 flow;
            flow.x = sin(uv.y * 3.0 + t * 0.40) + 0.5 * sin(uv.y * 6.3 - t * 0.27);
            flow.y = cos(uv.x * 2.6 + t * 0.33) + 0.5 * cos(uv.x * 5.7 + t * 0.31);
            vec2 p = uv + flow * 0.05;

            // Glow centres drift slowly on their own wide orbits.
            vec2 c1 = vec2(0.24 + 0.11 * sin(t * 0.13), 0.80 + 0.08 * cos(t * 0.11));
            vec2 c2 = vec2(0.80 + 0.11 * cos(t * 0.10), 0.18 + 0.09 * sin(t * 0.12));
            vec2 c3 = vec2(0.48 + 0.10 * sin(t * 0.08 + 1.5), 0.52 + 0.09 * cos(t * 0.09));

            // Large, soft radii and low intensities keep the colour subtle.
            float g1 = glow(p, c1, 0.72, aspect) * 0.05;
            float g2 = glow(p, c2, 0.74, aspect) * 0.032;
            float g3 = glow(p, c3, 0.62, aspect) * 0.02;

            vec3 col = base + leaf * g1 + amber * g2 + leaf * g3;

            // ±0.5/255 ordered-ish noise removes visible banding steps.
            col += (hash(gl_FragCoord.xy) - 0.5) / 255.0;

            gl_FragColor = vec4(col, 1.0);
        }
    `;

    onMount(() => {
        const gl = canvas.getContext('webgl', {
            antialias: false,
            alpha: false,
        });
        if (!gl) return;

        const prefersReduced = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        const compile = (type: number, src: string) => {
            const s = gl.createShader(type)!;
            gl.shaderSource(s, src);
            gl.compileShader(s);
            return s;
        };

        const prog = gl.createProgram()!;
        gl.attachShader(prog, compile(gl.VERTEX_SHADER, vert));
        gl.attachShader(prog, compile(gl.FRAGMENT_SHADER, frag));
        gl.linkProgram(prog);
        gl.useProgram(prog);

        const buf = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, buf);
        gl.bufferData(
            gl.ARRAY_BUFFER,
            new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]),
            gl.STATIC_DRAW,
        );
        const loc = gl.getAttribLocation(prog, 'p');
        gl.enableVertexAttribArray(loc);
        gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);

        const uRes = gl.getUniformLocation(prog, 'u_res');
        const uTime = gl.getUniformLocation(prog, 'u_time');

        const resize = () => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = Math.max(1, Math.round(canvas.clientWidth * dpr));
            const h = Math.max(1, Math.round(canvas.clientHeight * dpr));
            if (canvas.width !== w || canvas.height !== h) {
                canvas.width = w;
                canvas.height = h;
            }
            gl.viewport(0, 0, canvas.width, canvas.height);
        };

        const draw = (t: number) => {
            resize();
            gl.uniform2f(uRes, canvas.width, canvas.height);
            gl.uniform1f(uTime, t);
            gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        };

        // Size correctly before the first paint so we never upscale a tiny buffer.
        resize();

        let raf = 0;
        const start = performance.now();

        const ro = new ResizeObserver(() => {
            if (prefersReduced) draw(0);
        });
        ro.observe(canvas);

        if (prefersReduced) {
            draw(0);
        } else {
            const loop = (now: number) => {
                draw((now - start) / 1000);
                raf = requestAnimationFrame(loop);
            };
            raf = requestAnimationFrame(loop);
        }

        return () => {
            cancelAnimationFrame(raf);
            ro.disconnect();
            gl.getExtension('WEBGL_lose_context')?.loseContext();
        };
    });
</script>

<canvas
    bind:this={canvas}
    class="pointer-events-none absolute inset-0 z-0 h-full w-full"
    aria-hidden="true"
></canvas>
