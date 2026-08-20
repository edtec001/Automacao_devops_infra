/*
Em um motor 4 tempos, 1 bico injetor pulsa apenas 1 vez a cada 2 voltas do virabrequim, ou seja 0.5 pulsos por volta.
Se você tem 4,67 Hz o cálculo real é 4,67 * 60(segundos) * 2(voltas por pulso) = 560 rpm
Como o bico só "bate" a cada 2 voltas, cada pulso contado vale, na verdade, 2 voltas
Se amanhã você decidir pegar o sinal do contagiros original do carro (que geralmente envia 2 pulsos por volta em um 4 cilindros), você apenas muda voltasPorPulso para 0.5.
*/

void calc_pulso_rpm() {
  unsigned long agora = micros(); 
  unsigned long intervalo = agora - ultimoSalto;

  if (intervalo > 10000) { // Filtro anti-ruído (5K ignora > 12.000 RPM)
    frequenciaHz = 1000000.0 / intervalo;
    ultimoSalto = agora;
  }
}


void update_rpm() {
  float hzLocal;
  unsigned long agoraMicros = micros();

  noInterrupts();
  hzLocal = frequenciaHz;
  unsigned long tempoSemPulso = agoraMicros - ultimoSalto;
  interrupts();

  // Se ficar mais de 1.2 segundos sem pulso, o motor parou
  if (tempoSemPulso > 1200000) {
    hzLocal = 0;
    rpmFiltered = 0; // Zera o filtro para o ponteiro cair rápido
  }

  // Calcula dinamicamente
    float rpmBruto = hzLocal * 60.0 * voltas_por_pulso;

  // Filtro EMA (0.1 é bem suave, 0.2 é mais rápido)
  const float alpha = 0.15;
  rpmFiltered = (alpha * rpmBruto) + ((1.0 - alpha) * rpmFiltered);
  
  rpm = (uint16_t)rpmFiltered;
}
