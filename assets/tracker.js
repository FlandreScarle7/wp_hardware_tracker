document.addEventListener('DOMContentLoaded', () => {
    const detectBrowser = (ua) => {
        let name = 'unknown', version = 'unknown';

        // 优先级检测：微信 -> QQ -> 小米 -> Edge -> Firefox -> Chrome -> Safari
        const weChatMatch = ua.match(/MicroMessenger\/([\d.]+)/i);
        if (weChatMatch) return { name: 'wechat', version: weChatMatch[1] };

        const qqMatch = ua.match(/QQ\/([\d.]+)/i);
        if (qqMatch) return { name: 'qq', version: qqMatch[1] };

        const xiaomiMatch = ua.match(/MiuiBrowser\/([\d.]+)/i);
        if (xiaomiMatch) return { name: 'xiaomi', version: xiaomiMatch[1] };

        const edgeMatch = ua.match(/Edg\/([\d.]+)/i);
        if (edgeMatch) return { name: 'edge', version: edgeMatch[1] };

        const firefoxMatch = ua.match(/Firefox\/([\d.]+)/i);
        if (firefoxMatch) return { name: 'firefox', version: firefoxMatch[1] };

        const chromeMatch = ua.match(/Chrome\/([\d.]+)/i);
        if (chromeMatch) return { name: 'chrome', version: chromeMatch[1] };

        if (/Safari\//i.test(ua) && !/Chrome|Edg/i.test(ua)) {
            const safariVer = ua.match(/Version\/([\d.]+)/i);
            return { name: 'safari', version: safariVer ? safariVer[1] : 'unknown' };
        }

        return { name, version };
    };

const collectData = async () => {
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    let osName = 'unknown', osVersion = 'unknown', cpuArch = 'unknown';
    let uaCH = '{}';

    // 1. UA Client Hints
    if (hardwareTracker.ua_ch_enabled && navigator.userAgentData) {
        try {
            const hints = await navigator.userAgentData.getHighEntropyValues([
                'platform',
                'platformVersion',
                'architecture',
                'model',
                'uaFullVersion',
                'brands'
            ]);
            uaCH = JSON.stringify(hints);
            // 从 hints 中提取操作系统和 CPU 信息
            osName    = (hints.platform || '').toLowerCase();
            osVersion = hints.platformVersion || 'unknown';
            cpuArch   = hints.architecture    || 'unknown';
        } catch (e) {
            console.error('[硬件追踪] UA CH 获取失败：', e);
        }
    }

    // 2. 后备：User-Agent 字符串解析
    if (osName === 'unknown' || osVersion === 'unknown') {
        const ua = navigator.userAgent;
        if (/Windows NT 10/.test(ua))         { osName = 'windows'; osVersion = '10'; }
        else if (/Mac OS X 10_15/.test(ua))    { osName = 'macos';   osVersion = 'Catalina'; }
        else if (/iPhone OS 16_/.test(ua))    { osName = 'ios';     osVersion = '16'; }
    }

    // 3. Windows 和 macOS 版本映射
    if (osName === 'windows') {
        const major = parseInt(osVersion.split('.')[0], 10) || 0;
        osVersion = major >= 13 ? '11' : major > 0 ? '10' : '8.1';
    }
    if (osName === 'macos') {
        const map = { '13': 'Ventura', '12': 'Monterey', '11': 'Big Sur', '10.15': 'Catalina' };
        osVersion = map[osVersion.split('.')[0]] || osVersion;
    }

    // 4. CPU 架构后备检测
    if (cpuArch === 'unknown') {
        cpuArch = navigator.platform.includes('Win64')   ? 'x64'
                : /arm|aarch64/i.test(navigator.userAgent) ? 'arm'
                : 'x86';
    }

    // 5. GPU 检测
    let gpuVendor = 'unknown', gpuModel = 'unknown';
    try {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl');
        if (gl) {
            const info = gl.getExtension('WEBGL_debug_renderer_info');
            gpuVendor = gl.getParameter(info.UNMASKED_VENDOR_WEBGL);
            gpuModel  = gl.getParameter(info.UNMASKED_RENDERER_WEBGL);
        }
    } catch (e) { /* 静默失败 */ }

    // 6. 浏览器检测
    const { name: browserName, version: browserVersion } = detectBrowser(navigator.userAgent);

    // 7. 提交到后端
    fetch(hardwareTracker.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action:          'hardware_tracker',
            security:        hardwareTracker.security,
            os_name:         osName,
            os_version:      osVersion,
            cpu_arch:        cpuArch,
            cpu_cores:       navigator.hardwareConcurrency || 0,
            gpu_vendor:      gpuVendor,
            gpu_model:       gpuModel,
            timezone:        timezone,
            browser_name:    browserName,
            browser_version: browserVersion,
            ua_ch:           uaCH
        })
    });
};

setTimeout(() => {
        collectData().catch(error => {
            console.error('收集失败:', error);
        });
    }, 10);
});