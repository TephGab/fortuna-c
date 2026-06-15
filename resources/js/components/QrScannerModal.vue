<!-- resources/js/Components/QrScannerModal.vue -->
<!-- 
  QR Code Scanner Modal Component
  Features:
  - Real-time camera scanning
  - Image upload scanning
  - Parses email and amount from QR codes
  - Redirects to Send Money page with pre-filled data
  
  Supported QR Code Formats:
  1. Plain email: user@example.com
  2. JSON: {"email":"user@example.com","amount":50,"currency":"USD"}
-->

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 dark:bg-gray-900">
            <!-- Modal Header -->
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Scan QR Code') }}</h3>
                <button @click="close" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-800">
                    <X class="h-5 w-5 text-gray-500" />
                </button>
            </div>
            
            <!-- ==================== CAMERA SCANNER SECTION ==================== -->
            <div v-if="hasCamera" class="mb-4">
                <!-- Start Camera Button -->
                <button
                    @click="startCamera"
                    class="mb-3 flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 py-3 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                >
                    <Camera class="h-5 w-5" />
                    <span>{{ t('Use Camera') }}</span>
                </button>
                
                <!-- Camera Preview with Scanning Overlay -->
                <div v-if="showCamera" class="relative overflow-hidden rounded-xl">
                    <video ref="videoRef" class="h-64 w-full object-cover" autoplay playsinline></video>
                    <!-- Scanning Area Overlay -->
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-xl border-2 border-blue-500">
                        <div class="h-48 w-48 rounded-lg border-2 border-blue-500"></div>
                    </div>
                    <!-- Capture Button -->
                    <button
                        @click="captureFromCamera"
                        class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-blue-600 p-3 text-white shadow-lg"
                    >
                        <Camera class="h-6 w-6" />
                    </button>
                </div>
            </div>
            
            <!-- Divider (shown when camera is active) -->
            <div v-if="hasCamera && showCamera" class="relative my-4 text-center">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300 dark:border-gray-700"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="bg-white px-2 text-gray-500 dark:bg-gray-900 dark:text-gray-400">{{ t('or') }}</span>
                </div>
            </div>
            
            <!-- ==================== IMAGE UPLOAD SECTION ==================== -->
            <div class="rounded-xl border-2 border-dashed border-gray-300 p-8 text-center dark:border-gray-600">
                <QrCode class="mx-auto h-16 w-16 text-gray-400" />
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    {{ t('Upload a QR code image to pay') }}
                </p>
                <label class="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600">
                    <Upload class="h-4 w-4" />
                    {{ t('Choose Image') }}
                    <input 
                        type="file" 
                        accept="image/*" 
                        class="hidden" 
                        @change="handleFileUpload"
                        ref="fileInput"
                    />
                </label>
            </div>
            
            <!-- Help Text -->
            <p class="mt-4 text-center text-xs text-gray-500">
                {{ t('Make sure the QR code is clear and well-lit') }}
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { X, Camera, Upload, QrCode } from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';
import { router } from '@inertiajs/vue3';
import jsQR from 'jsqr';

const { t } = useTranslation();

// ============================================================================
// PROPS & EMITS
// ============================================================================

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits(['close', 'decoded']);

// ============================================================================
// STATE VARIABLES
// ============================================================================

const videoRef = ref<HTMLVideoElement | null>(null);      // Video element reference
const fileInput = ref<HTMLInputElement | null>(null);     // File input reference
const showCamera = ref(false);                            // Whether camera preview is shown
const hasCamera = ref(false);                             // Whether device has camera
let stream: MediaStream | null = null;                    // Camera stream
let animationId: number | null = null;                    // Animation frame ID for continuous scanning

// ============================================================================
// MODAL CONTROLS
// ============================================================================

/**
 * Close the modal and clean up camera
 */
const close = () => {
    stopCamera();
    emit('close');
};

// ============================================================================
// CAMERA SCANNING METHODS
// ============================================================================

/**
 * Start the camera and begin scanning for QR codes
 */
const startCamera = async () => {
    try {
        // Request camera access with environment (back) camera
        stream = await navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: 'environment' } 
        });
        
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            await videoRef.value.play();
            showCamera.value = true;
            startScanning(); // Start continuous scanning
        }
    } catch (err) {
        console.error('Camera error:', err);
        alert(t('Unable to access camera'));
    }
};

/**
 * Stop the camera and clean up resources
 */
const stopCamera = () => {
    // Cancel animation frame
    if (animationId) {
        cancelAnimationFrame(animationId);
        animationId = null;
    }
    
    // Stop all camera tracks
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    
    // Clear video source
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
    
    showCamera.value = false;
};

/**
 * Continuously scan the camera feed for QR codes
 * Uses requestAnimationFrame for smooth scanning
 */
const startScanning = () => {
    const scan = () => {
        if (!videoRef.value || !showCamera.value) return;
        
        const canvas = document.createElement('canvas');
        const video = videoRef.value;
        
        // Wait for video to have enough data
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            
            if (ctx) {
                // Draw current video frame to canvas
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                
                // Try to decode QR code from the image
                const code = jsQR(imageData.data, canvas.width, canvas.height);
                
                if (code) {
                    // QR code found! Process it
                    processDecodedData(code.data);
                    stopCamera();
                    close();
                }
            }
        }
        
        // Continue scanning
        animationId = requestAnimationFrame(scan);
    };
    
    scan();
};

/**
 * Capture a single frame from camera and decode QR code
 * Used as an alternative to continuous scanning
 */
const captureFromCamera = () => {
    if (!videoRef.value) return;
    
    const canvas = document.createElement('canvas');
    const video = videoRef.value;
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    
    if (ctx) {
        // Draw current frame
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        
        // Try to decode QR code
        const code = jsQR(imageData.data, canvas.width, canvas.height);
        
        if (code) {
            processDecodedData(code.data);
            stopCamera();
            close();
        } else {
            alert(t('No QR code found. Please try again.'));
        }
    }
};

// ============================================================================
// IMAGE UPLOAD SCANNING
// ============================================================================

/**
 * Handle file upload - decode QR code from uploaded image
 */
const handleFileUpload = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;
    
    const file = input.files[0];
    const img = new Image();
    const imageUrl = URL.createObjectURL(file);
    
    img.onload = () => {
        // Create canvas to read image pixels
        const canvas = document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext('2d');
        
        if (ctx) {
            ctx.drawImage(img, 0, 0, img.width, img.height);
            const imageData = ctx.getImageData(0, 0, img.width, img.height);
            
            // Try to decode QR code from the uploaded image
            const code = jsQR(imageData.data, img.width, img.height);
            
            if (code) {
                console.log('QR Code decoded from image:', code.data);
                processDecodedData(code.data);
                close();
            } else {
                alert(t('No QR code found in the image. Please try another image.'));
            }
        }
        
        // Clean up object URL
        URL.revokeObjectURL(imageUrl);
    };
    
    img.onerror = () => {
        alert(t('Failed to load image. Please try another file.'));
        URL.revokeObjectURL(imageUrl);
    };
    
    img.src = imageUrl;
};

// ============================================================================
// QR CODE DATA PROCESSING
// ============================================================================

/**
 * Process decoded QR code data
 * 
 * Supported QR code formats:
 * 1. Plain email: user@example.com
 * 2. JSON: {"email":"user@example.com","amount":50,"currency":"USD"}
 * 
 * Extracts recipient email, amount, and description
 * Redirects to Send Money page with pre-filled data
 */
const processDecodedData = (data: string) => {
    console.log('Decoded QR data:', data);
    
    let recipientEmail = '';
    let amount: number | null = null;
    let description = '';
    
    // ========================================================================
    // CASE 1: Try to parse as JSON (includes amount)
    // ========================================================================
    try {
        const qrData = JSON.parse(data);
        
        // Extract email from various possible field names
        if (qrData.email) {
            recipientEmail = qrData.email;
        } else if (qrData.user_email) {
            recipientEmail = qrData.user_email;
        } else if (qrData.requester_email) {
            recipientEmail = qrData.requester_email;
        }
        
        // Extract amount if present
        if (qrData.amount && qrData.amount > 0) {
            amount = qrData.amount;
            console.log('Amount extracted from JSON:', amount);
        }
        
        // Extract description if present
        if (qrData.description) {
            description = qrData.description;
        }
        
    } catch (err) {
        // Not JSON, continue to next case
        console.log('Not JSON format, trying plain text');
    }
    
    // ========================================================================
    // CASE 2: Plain text containing email
    // ========================================================================
    if (!recipientEmail) {
        const emailMatch = data.match(/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/);
        if (emailMatch) {
            recipientEmail = emailMatch[1];
            console.log('Email extracted from text:', recipientEmail);
        }
    }
    
    // ========================================================================
    // REDIRECT TO SEND MONEY PAGE WITH PRE-FILLED DATA
    // ========================================================================
    if (recipientEmail) {
        // Build query parameters for the Send Money page
        const params = new URLSearchParams();
        params.append('recipient_email', recipientEmail);
        
        if (amount && amount > 0) {
            params.append('amount', amount.toString());
        }
        
        if (description) {
            params.append('description', description);
        }
        
        console.log('Redirecting to Send Money with params:', params.toString());
        
        // Emit decoded event for parent components
        emit('decoded', { 
            type: 'user', 
            email: recipientEmail,
            amount: amount,
            description: description
        });
        
        // Redirect to Send Money page with pre-filled data
        router.visit(`/transfers?${params.toString()}`);
        return;
    }
    
    // ========================================================================
    // ERROR: No email found in QR code
    // ========================================================================
    alert(t('Invalid QR code. Please scan a valid payment QR code containing an email address.'));
};

// ============================================================================
// LIFECYCLE HOOKS
// ============================================================================

/**
 * Check if device has a camera when component mounts
 */
onMounted(() => {
    navigator.mediaDevices.enumerateDevices()
        .then(devices => {
            hasCamera.value = devices.some(device => device.kind === 'videoinput');
        })
        .catch(() => {
            hasCamera.value = false;
        });
});

/**
 * Clean up camera when component unmounts
 */
onUnmounted(() => {
    stopCamera();
});
</script>