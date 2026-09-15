allprojects {
    repositories {
        google()
        mavenCentral()
    }
}

val newBuildDir: Directory =
    rootProject.layout.buildDirectory
        .dir("../../build")
        .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}
subprojects {
    project.evaluationDependsOn(":app")
}

fun Project.forceCompileSdk36() {
    val android = extensions.findByName("android") ?: return
    val setter = android.javaClass.methods.firstOrNull {
        it.name == "setCompileSdk" && it.parameterCount == 1
    } ?: android.javaClass.methods.firstOrNull {
        it.name == "setCompileSdkVersion" && it.parameterCount == 1
    } ?: return
    try {
        setter.invoke(android, 36)
    } catch (_: Exception) {
        // Ignore plugins that expose a different compileSdk setter.
    }
}

subprojects {
    if (state.executed) {
        forceCompileSdk36()
    } else {
        afterEvaluate { forceCompileSdk36() }
    }
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
