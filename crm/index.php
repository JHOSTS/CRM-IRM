<?php
require_once __DIR__ . '/includes/auth.php';

// Se já está logado, redireciona para o kanban
if (getSessionUser()) {
    header('Location: /crm/kanban.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CRM — Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/crm/assets/css/style.css">
  <style>
    /* Override style.css backgrounds so the shader canvas shows through */
    body         { background: transparent !important; }
    .login-page  { background: transparent !important; position: relative; z-index: 1; }

    #shader-bg {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      z-index: 0;
      display: block;
    }
    .login-card {
      background: rgba(10, 8, 28, 0.78);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(255,255,255,0.09);
      box-shadow: 0 8px 40px rgba(0,0,0,.6);
    }
    .login-card .form-control {
      background: rgba(255,255,255,0.06);
      border-color: rgba(255,255,255,0.12);
    }
    .login-card .form-control:focus {
      background: rgba(255,255,255,0.1);
    }
    .login-logo h1 { color: #fff; }
  </style>
</head>
<body>

<canvas id="shader-bg"></canvas>

<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <h1>CRM <span style="color:var(--accent)">IRM</span></h1>
      <p>Gestão de relacionamentos e negociações</p>
    </div>

    <div id="login-error" class="login-error hidden"></div>

    <form id="login-form" autocomplete="off">
      <div class="form-group">
        <label class="form-label" for="email">E-mail</label>
        <input class="form-control" type="email" id="email" name="email"
               placeholder="seu@email.com" autocomplete="username" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="senha">Senha</label>
        <input class="form-control" type="password" id="senha" name="senha"
               placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary w-100" id="btn-login" style="margin-top:8px;">
        Entrar
      </button>
    </form>
  </div>
</div>

<script>
// ── WebGL Shader Background ──────────────────────────────────────────────────
(function() {
  const canvas = document.getElementById('shader-bg');
  const gl = canvas.getContext('webgl');
  if (!gl) return; // WebGL não suportado — fundo escuro do CSS serve como fallback

  const VS = `
    attribute vec4 aPos;
    void main() { gl_Position = aPos; }
  `;

  const FS = `
    precision highp float;
    uniform vec2  iResolution;
    uniform float iTime;

    const float overallSpeed      = 0.2;
    const float gridSmoothWidth   = 0.015;
    const float axisWidth         = 0.05;
    const float majorLineWidth    = 0.025;
    const float minorLineWidth    = 0.0125;
    const float majorLineFreq     = 5.0;
    const float minorLineFreq     = 1.0;
    const float scale             = 5.0;
    const vec4  lineColor         = vec4(0.4, 0.2, 0.8, 1.0);
    const float minLineWidth      = 0.01;
    const float maxLineWidth      = 0.2;
    const float lineSpeed         = 1.0  * overallSpeed;
    const float lineAmplitude     = 1.0;
    const float lineFrequency     = 0.2;
    const float warpSpeed         = 0.2  * overallSpeed;
    const float warpFrequency     = 0.5;
    const float warpAmplitude     = 1.0;
    const float offsetFrequency   = 0.5;
    const float offsetSpeed       = 1.33 * overallSpeed;
    const float minOffsetSpread   = 0.6;
    const float maxOffsetSpread   = 2.0;
    const int   linesPerGroup     = 16;

    #define drawSmoothLine(pos,hw,t)  smoothstep(hw, 0.0, abs(pos-(t)))
    #define drawCrispLine(pos,hw,t)   smoothstep(hw+gridSmoothWidth, hw, abs(pos-(t)))
    #define drawPeriodicLine(f,w,t)   drawCrispLine(f/2.0, w, abs(mod(t,f)-(f)/2.0))
    #define drawCircle(p,r,c)         smoothstep(r+gridSmoothWidth, r, length(c-(p)))

    float random(float t) {
      return (cos(t) + cos(t*1.3+1.3) + cos(t*1.4+1.4)) / 3.0;
    }

    float getPlasmaY(float x, float hFade, float offset) {
      return random(x * lineFrequency + iTime * lineSpeed) * hFade * lineAmplitude + offset;
    }

    void main() {
      vec2 uv    = gl_FragCoord.xy / iResolution.xy;
      vec2 space = (gl_FragCoord.xy - iResolution.xy * 0.5) / iResolution.x * 2.0 * scale;

      float hFade = 1.0 - (cos(uv.x * 6.28318) * 0.5 + 0.5);
      float vFade = 1.0 - (cos(uv.y * 6.28318) * 0.5 + 0.5);

      space.y += random(space.x * warpFrequency + iTime * warpSpeed) * warpAmplitude * (0.5 + hFade);
      space.x += random(space.y * warpFrequency + iTime * warpSpeed + 2.0) * warpAmplitude * hFade;

      vec4 lines = vec4(0.0);
      for (int l = 0; l < 16; l++) {
        float ni  = float(l) / float(linesPerGroup);
        float oPos = float(l) + space.x * offsetFrequency;
        float rand = random(oPos + iTime * offsetSpeed) * 0.5 + 0.5;
        float hw   = mix(minLineWidth, maxLineWidth, rand * hFade) * 0.5;
        float off  = random(oPos + iTime * offsetSpeed * (1.0 + ni)) * mix(minOffsetSpread, maxOffsetSpread, hFade);
        float lpos = getPlasmaY(space.x, hFade, off);
        float line = drawSmoothLine(lpos, hw, space.y) * 0.5
                   + drawCrispLine(lpos, hw * 0.15, space.y);

        float cx = mod(float(l) + iTime * lineSpeed, 25.0) - 12.0;
        vec2  cp = vec2(cx, getPlasmaY(cx, hFade, off));
        line += drawCircle(cp, 0.01, space) * 4.0;

        lines += line * lineColor * rand;
      }

      vec4 bg = mix(vec4(0.1,0.1,0.3,1.0), vec4(0.3,0.1,0.5,1.0), uv.x);
      bg *= vFade;
      bg.a = 1.0;
      gl_FragColor = bg + lines;
    }
  `;

  function compileShader(type, src) {
    const s = gl.createShader(type);
    gl.shaderSource(s, src);
    gl.compileShader(s);
    if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) {
      console.error(gl.getShaderInfoLog(s));
      gl.deleteShader(s);
      return null;
    }
    return s;
  }

  const prog = gl.createProgram();
  gl.attachShader(prog, compileShader(gl.VERTEX_SHADER,   VS));
  gl.attachShader(prog, compileShader(gl.FRAGMENT_SHADER, FS));
  gl.linkProgram(prog);
  if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
    console.error(gl.getProgramInfoLog(prog)); return;
  }

  const buf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, buf);
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 1,-1, -1,1, 1,1]), gl.STATIC_DRAW);

  const aPos   = gl.getAttribLocation(prog, 'aPos');
  const uRes   = gl.getUniformLocation(prog, 'iResolution');
  const uTime  = gl.getUniformLocation(prog, 'iTime');

  function resize() {
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;
    gl.viewport(0, 0, canvas.width, canvas.height);
  }
  window.addEventListener('resize', resize);
  resize();

  const t0 = Date.now();
  function render() {
    gl.useProgram(prog);
    gl.uniform2f(uRes, canvas.width, canvas.height);
    gl.uniform1f(uTime, (Date.now() - t0) / 1000);
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, 0, 0);
    gl.enableVertexAttribArray(aPos);
    gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    requestAnimationFrame(render);
  }
  requestAnimationFrame(render);
})();

// ── Login Form ───────────────────────────────────────────────────────────────
document.getElementById('login-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn   = document.getElementById('btn-login');
  const errEl = document.getElementById('login-error');
  errEl.classList.add('hidden');
  btn.disabled = true;
  btn.textContent = 'Entrando…';

  try {
    const res = await fetch('/crm/api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email:    document.getElementById('email').value,
        senha:    document.getElementById('senha').value,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      }),
    });
    const data = await res.json();
    if (res.ok && data.success) {
      sessionStorage.setItem('just_logged_in', '1');
      window.location.href = '/crm/kanban.php';
    } else {
      errEl.textContent = data.error || 'Erro ao fazer login.';
      errEl.classList.remove('hidden');
    }
  } catch {
    errEl.textContent = 'Falha de conexão. Tente novamente.';
    errEl.classList.remove('hidden');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Entrar';
  }
});
</script>
</body>
</html>
