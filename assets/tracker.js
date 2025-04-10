document.addEventListener('DOMContentLoaded', () => {
    const collectData = async () => {
        // 基础信息
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        let osName = 'unknown', osVersion = 'unknown';

        // 操作系统检测
        if (navigator.userAgentData) {
            try {
                const data = await navigator.userAgentData.getHighEntropyValues([
                    'platform', 
                    'platformVersion',
                    'architecture'
                ]);
                
                osName = data.platform.toLowerCase();
                osVersion = data.platformVersion;

                // Windows版本处理
                if (osName === 'windows') {
                    const majorVer = parseInt(osVersion.split('.')[0]);
                    osVersion = majorVer >= 13 ? '11' : majorVer > 0 ? '10' : '8.1';
                }

                // macOS版本转换
                if (osName === 'macos') {
                    const macVersionMap = {
                        '13': 'Ventura', '12': 'Monterey', 
                        '11': 'Big Sur', '10.15': 'Catalina'
                    };
                    osVersion = macVersionMap[osVersion.split('.')[0]] || osVersion;
                }
            } catch(e) {
                console.error('High Entropy API error:', e);
            }
        } else {
            // 传统User-Agent分析
            const ua = navigator.userAgent;
            if (/Windows NT 10/.test(ua)) osName = 'windows', osVersion = '10';
            if (/Mac OS X 10_15/.test(ua)) osName = 'macos', osVersion = 'Catalina';
            if (/iPhone OS 16_/.test(ua)) osName = 'ios', osVersion = '16';
        }

        // CPU架构检测
        let cpuArch = 'unknown';
        if (navigator.userAgentData?.architecture) {
            cpuArch = navigator.userAgentData.architecture;
        } else {
            cpuArch = navigator.platform.includes('Win64') ? 'x64' : 
                     /arm|aarch64/i.test(navigator.userAgent) ? 'arm' : 'x86';
        }

        // GPU检测
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
            console.error('GPU detection failed:', e);
        }

        // 发送数据
        fetch(hardwareTracker.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'hardware_tracker',
                security: hardwareTracker.security,
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

    // 延迟2秒执行避免影响首屏加载
    collectData();
});
