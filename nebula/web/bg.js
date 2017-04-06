/** Refer to http://codepen.io/jackrugile/pen/BjBGoM **/
var canvas = document.getElementById('canvas'),
    ctx = canvas.getContext('2d'),
    stars = [],
    count = 0,
    maxStars = 512
canvas.width = screen.width
canvas.height = screen.height

function random(n) {
    return Math.floor(Math.random() * (n + 1))
}

function orbit(x, y) {
    /*
    var max = Math.max(x, y),
        diameter = Math.round(Math.sqrt(max * max + max * max))
    return random(diameter / 2)
    */
    var u = 0.0,
        v = 0.0,
        w = 0.0,
        c = 0.0
    do {
        u = Math.random() * 2 - 1.0
        v = Math.random() * 2 - 1.0
        w = u * u + v * v
    } while (w == 0.0 || w >= 1.0)
    // Box-Muller
    c = Math.sqrt((-2 * Math.log(w)) / w)
    return x + (u * c * y)
}

var Star = function() {
    this.meteor = false
    this.canvas = document.createElement('canvas')
    var sctx = this.canvas.getContext('2d')
    this.canvas.width = 50
    this.canvas.height = 50

    this.orbitRadius = orbit(canvas.width / 4, canvas.width / 8)
        // this.orbitRadius = orbit(screen.width, screen.height)
    this.radius = (random(this.orbitRadius - 1) + 1) / 12
    this.radius = this.radius > 30 ? 30 : this.radius
    this.orbitX = canvas.width / 2
    this.orbitY = canvas.height / 2
    this.timePassed = random(maxStars)
    if (random(1000) < 30) {
        this.meteor = true
        this.speed = random(this.orbitRadius) / 50000
    } else {
        this.speed = random(this.orbitRadius) / 5000000
    }
    // this.speed = random(this.orbitRadius) / 50000
    this.alpha = (random(8) + 2) / 10
    this.hue = random(360)

    var half = this.canvas.width / 2
    var gradient = sctx.createRadialGradient(half, half, 0, half, half, half)
    gradient.addColorStop(0.025, '#fff')
    gradient.addColorStop(0.1, 'hsl(' + this.hue + ', 61%, 60%)')
    gradient.addColorStop(0.25, 'hsl(' + this.hue + ', 64%, 6%)')
    gradient.addColorStop(1, 'transparent')

    sctx.fillStyle = gradient
    sctx.beginPath()
    sctx.arc(half, half, half, 0, Math.PI * 2)
    sctx.fill()

    count++
    stars[count] = this
}

Star.prototype.draw = function() {
    var seed = Math.random()
    var x = this.orbitX - Math.sin(this.timePassed + 0.5) * this.orbitRadius,
        y = Math.cos(this.timePassed) * this.orbitRadius / 2 + this.orbitY,
        twinkle = Math.floor(5 * seed)

    if (twinkle === 1 && this.alpha > 0) {
        this.alpha -= 0.01
    } else if (twinkle === 2 && this.alpha < 1) {
        this.alpha += 0.01
    }

    ctx.globalAlpha = this.alpha
    ctx.drawImage(this.canvas, x - this.radius / 2, y - this.radius / 2, this.radius / 2, this.radius / 2)
    this.timePassed += this.speed
    this.orbitRadius += this.speed * 50
    if (this.meteor) {
        if (this.orbitRadius > 3000) {
            if (Math.floor(10 * seed) < 5) {
                this.orbitRadius = Math.floor(500 * seed)
                this.meteor = false
                this.speed = Math.floor(this.orbitRadius * seed) / 5000000
            } else {
                this.orbitRadius = Math.floor(800 * seed)
            }
        }
    } else {
        if (Math.floor(50000000 * seed) < 10) {
            this.meteor = true
            this.speed = this.orbitRadius / 80000
        }
    }
}

function animation() {
    if (count < maxStars && random(15) == 1) {
        new Star()
    }
    ctx.globalCompositeOperation = 'source-over'
    ctx.globalAlpha = 0.8
    ctx.fillStyle = '#232323'
    ctx.fillRect(0, 0, canvas.width, canvas.height)

    ctx.globalCompositeOperation = 'lighter'
    for (var i = 1, l = count; i < l; i++) {
        stars[i].draw()
    }
    window.requestAnimationFrame(animation)
}
animation()