package com.example.attendance

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Bundle
import android.webkit.*
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat

/**
 * Enterprise Mobile Attendance Client
 * Features:
 * - Hardware Biometric Authentication Bridge (Fingerprint / Face Unlock)
 * - Geolocation capture for attendance punch-in
 * - File upload via native picker for document submission
 * - Custom User-Agent injection for server-side device verification
 */
class MainActivity : AppCompatActivity() {

    private lateinit var myWebView: WebView
    
    // Configurable Application Endpoint (replace with your production URL)
    private val appUrl = "https://your-company-portal.example.com/mobile/attendance.html"

    private var filePathCallback: ValueCallback<Array<Uri>>? = null
    private val FILE_CHOOSER_RESULT_CODE = 1

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        myWebView = findViewById(R.id.webview)
        val webSettings: WebSettings = myWebView.settings
        webSettings.javaScriptEnabled = true
        webSettings.domStorageEnabled = true
        webSettings.setGeolocationEnabled(true)
        webSettings.cacheMode = WebSettings.LOAD_NO_CACHE

        // Inject custom User-Agent tag for secure server-side app detection
        webSettings.userAgentString = webSettings.userAgentString + " ENTERPRISE_MOBILE_APP"

        // Screen fitting & responsive viewport configuration
        webSettings.useWideViewPort = true
        webSettings.loadWithOverviewMode = true
        webSettings.builtInZoomControls = false
        webSettings.displayZoomControls = false

        myWebView.webViewClient = WebViewClient()

        // ChromeClient handling Geolocation and File Attachments
        myWebView.webChromeClient = object : WebChromeClient() {
            override fun onGeolocationPermissionsShowPrompt(
                origin: String, 
                callback: GeolocationPermissions.Callback
            ) {
                // Grant geolocation access to the webview
                callback.invoke(origin, true, false)
            }

            // Handles file upload prompts from <input type="file">
            override fun onShowFileChooser(
                webView: WebView?,
                filePathCallback: ValueCallback<Array<Uri>>?,
                fileChooserParams: FileChooserParams?
            ): Boolean {
                this@MainActivity.filePathCallback?.onReceiveValue(null)
                this@MainActivity.filePathCallback = filePathCallback

                // Ensure storage permission is requested
                if (ContextCompat.checkSelfPermission(
                        this@MainActivity, 
                        Manifest.permission.READ_EXTERNAL_STORAGE
                    ) != PackageManager.PERMISSION_GRANTED) {
                    ActivityCompat.requestPermissions(
                        this@MainActivity, 
                        arrayOf(Manifest.permission.READ_EXTERNAL_STORAGE), 
                        2
                    )
                }

                val intent = fileChooserParams?.createIntent()
                try {
                    if (intent != null) {
                        startActivityForResult(intent, FILE_CHOOSER_RESULT_CODE)
                    } else {
                        // Fallback generic file chooser intent
                        val fallbackIntent = Intent(Intent.ACTION_GET_CONTENT).apply {
                            addCategory(Intent.CATEGORY_OPENABLE)
                            type = "*/*"
                        }
                        startActivityForResult(
                            Intent.createChooser(fallbackIntent, "Select Document"), 
                            FILE_CHOOSER_RESULT_CODE
                        )
                    }
                } catch (e: Exception) {
                    this@MainActivity.filePathCallback?.onReceiveValue(null)
                    this@MainActivity.filePathCallback = null
                    return false
                }
                return true
            }
        }

        // Register Native Biometric Bridge to JavaScript runtime
        val biometricInterface = BiometricInterface(this)
        myWebView.addJavascriptInterface(biometricInterface, "AndroidBiometric")

        requestLocationPermission()
        myWebView.loadUrl(appUrl)
    }

    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode == FILE_CHOOSER_RESULT_CODE) {
            if (filePathCallback == null) return
            val results = WebChromeClient.FileChooserParams.parseResult(resultCode, data)
            filePathCallback?.onReceiveValue(results)
            filePathCallback = null
        }
    }

    private fun requestLocationPermission() {
        if (ContextCompat.checkSelfPermission(
                this, 
                Manifest.permission.ACCESS_FINE_LOCATION
            ) != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(
                this, 
                arrayOf(Manifest.permission.ACCESS_FINE_LOCATION), 
                1
            )
        }
    }

    override fun onBackPressed() {
        if (myWebView.canGoBack()) {
            myWebView.goBack()
        } else {
            super.onBackPressed()
        }
    }
}
