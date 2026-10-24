package com.example.attendance

import android.webkit.JavascriptInterface
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import java.util.concurrent.Executor

/**
 * Native Android Biometric Bridge
 * 
 * Exposes hardware biometric authentication (Fingerprint, Face Unlock)
 * to web applications loaded within Android WebView via @JavascriptInterface.
 */
class BiometricInterface(private val activity: FragmentActivity) {

    private val executor: Executor = ContextCompat.getMainExecutor(activity)

    /**
     * Checks if biometric hardware is present, enrolled, and ready.
     * Callable from web JS: AndroidBiometric.isBiometricAvailable()
     */
    @JavascriptInterface
    fun isBiometricAvailable(): Boolean {
        val biometricManager = BiometricManager.from(activity)
        return when (biometricManager.canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_STRONG)) {
            BiometricManager.BIOMETRIC_SUCCESS -> true
            else -> false
        }
    }

    /**
     * Triggers the native system biometric prompt.
     * Callable from web JS: AndroidBiometric.showBiometricPrompt('onBiometricComplete')
     * 
     * @param callbackName JavaScript function name to invoke on completion
     */
    @JavascriptInterface
    fun showBiometricPrompt(callbackName: String) {
        activity.runOnUiThread {
            val biometricPrompt = BiometricPrompt(
                activity, 
                executor, 
                object : BiometricPrompt.AuthenticationCallback() {
                    override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                        super.onAuthenticationError(errorCode, errString)
                        sendResultToWeb(callbackName, false, errString.toString())
                    }

                    override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                        super.onAuthenticationSucceeded(result)
                        sendResultToWeb(callbackName, true, "Success")
                    }

                    override fun onAuthenticationFailed() {
                        super.onAuthenticationFailed()
                        // Biometric attempt failed (finger not recognized). User can retry.
                    }
                }
            )

            val promptInfo = BiometricPrompt.PromptInfo.Builder()
                .setTitle("Biometric Authentication")
                .setSubtitle("Verify your identity to authenticate attendance")
                .setNegativeButtonText("Cancel")
                .build()

            biometricPrompt.authenticate(promptInfo)
        }
    }

    /**
     * Executes callback function in the WebView DOM with status and message.
     */
    private fun sendResultToWeb(callbackName: String, success: Boolean, message: String) {
        activity.runOnUiThread {
            val webView = activity.findViewById<android.webkit.WebView>(R.id.webview)
            webView?.evaluateJavascript(
                "if(typeof window.$callbackName === 'function') window.$callbackName($success, '$message');", 
                null
            )
        }
    }
}
