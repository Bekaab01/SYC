<style>
.floating-elements {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    overflow: hidden;
    z-index: 1;
}
.floating-element {
    position: absolute;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}
.element-1 {
    width: 100px;
    height: 100px;
    top: 10%;
    left: 20%;
    animation: float 15s infinite ease-in-out;
}
.element-2 {
    width: 150px;
    height: 150px;
    top: 60%;
    left: 10%;
    animation: float 18s infinite ease-in-out 1s;
}
.element-3 {
    width: 80px;
    height: 80px;
    top: 30%;
    right: 15%;
    animation: float 12s infinite ease-in-out 2s;
}
.element-4 {
    width: 120px;
    height: 120px;
    bottom: 10%;
    right: 20%;
    animation: float 20s infinite ease-in-out 3s;
}
@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    33% { transform: translateY(-20px) rotate(5deg); }
    66% { transform: translateY(20px) rotate(-5deg); }
}
</style>

<div class="floating-elements">
    <div class="floating-element element-1"></div>
    <div class="floating-element element-2"></div>
    <div class="floating-element element-3"></div>
    <div class="floating-element element-4"></div>
</div>
