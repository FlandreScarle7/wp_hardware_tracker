document.addEventListener('DOMContentLoaded', () => {
    const collectData = async () => {
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        let osName = 'unknown', osVersion = 'unknown';

        if (navigator.userAgentData) {
            try {
                const data = await navigator.userAgentData.getHighEntropyValues([
                    'platform', 
                    'platformVersion',
                    'architecture'
                ]);
                
                osName = data.platform.toLowerCase();
                osVersion = data.platformVersion;

                if (osName === 'windows') {
                    const majorVer = parseInt(osVersion.split('.')[0]);
                    osVersion = majorVer >= 13 ? '11' : majorVer > 0 ? '10' : '8.1';
                }

                if (osName === 'macos') {
                    const macVersionMap = {
                        '13': 'Ventura', '12': 'Monterey', 
                        '11': 'Big Sur', '10.15': 'Catalina'
                    };
                    osVersion = macVersionMap[osVersion.split('.')[0]] || osVersion;
                }
            } catch(e) {
                // 错误静默处理
            }
        } else {
            const ua = navigator.userAgent;
            if (/Windows NT 10/.test(ua)) osName = 'windows', osVersion = '10';
            if (/Mac OS X 10_15/.test(ua)) osName = 'macos', osVersion = 'Catalina';
            if (/iPhone OS 16_/.test(ua)) osName = 'ios', osVersion = '16';
        }

        let cpuArch = 'unknown';
        if (navigator.userAgentData?.architecture) {
            cpuArch = navigator.userAgentData.architecture;
        } else {
            cpuArch = navigator.platform.includes('Win64') ? 'x64' : 
                     /arm|aarch64/i.test(navigator.userAgent) ? 'arm' : 'x86';
        }

        let gpuVendor = 'unknown', gpuModel = 'unknown';
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl');
            if (gl) {
                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                gpuVendor = gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL);
                gpuModel = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
            }
        } catch(e) {
            // 错误静默处理
        }

        fetch(hardwareTrackerDev.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'hardware_tracker_dev',
                security: hardwareTrackerDev.security,
                os_name: osName,
                os_version: osVersion,
                cpu_arch: cpuArch,
                cpu_cores: navigator.hardwareConcurrency || 0,
                gpu_vendor: gpuVendor,
                gpu_model: gpuModel,
                timezone: timezone
            })
        });
    };

    setTimeout(collectData, 300);
    # 延迟300ms错开稳定版
});
