volatile uint16_t speedPulses = 0;
unsigned long lastSpeedTime = 0;

void calc_pulso_velo() {
  speedPulses++;
}

void update_speed() {

  static unsigned long lastTime = 0;
  unsigned long now = millis();

  if (now - lastTime >= 500) {
    noInterrupts();
    uint16_t p = speedPulses;
    speedPulses = 0;
    interrupts();


    float circ = 3.1416 * diametroPneu_mm / 1000.0;
    float v = (p / (float)ppv_roda) * circ * 3.6 / ((now - lastTime) / 1000.0);


    // --------------------
    // Aplica Média móvel
    // --------------------    

    speedBuffer[speedIndex++] = (uint16_t)v;

    if (speedIndex >= SPEED_SAMPLES) speedIndex = 0;

    uint32_t soma = 0;
    for (byte i = 0; i < SPEED_SAMPLES; i++) soma += speedBuffer[i];
    velocidade = soma / SPEED_SAMPLES;
    lastTime = now;
  }

}
