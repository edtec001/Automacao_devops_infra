unsigned long tempoAnterior = 0;
const long intervalo = 1000; // 1 segundo

void update_combustivel(){

unsigned long tempoAtual = millis();

if (tempoAtual - tempoAnterior >= intervalo) {
    tempoAnterior = tempoAtual;

      
  // 1. Leitura com média para estabilidade
  long somaADC_COMB = 0;
  for(int i = 0; i < 100; i++) {
    somaADC_COMB += analogRead(PINO_FUEL);
    delay(5);
  }
  float mediaADC_COMB = (float)somaADC_COMB / 100.0;

  // 2. Converter Leitura ADC para Ohms Reais
  // Evita divisão por zero se o fio soltar
  if (mediaADC_COMB == 0) mediaADC_COMB = 1; 
  
  float ohmsAtual = R_FIXO / ((1023.0 / mediaADC_COMB) - 1.0);

  // 3. Calcular Porcentagem baseada nos intervalos
  
  if (ohmsAtual <= OHMS_MEIO) {
    // Mapeia de Cheio (52) até Meio (175) -> 100% a 50%
    pct_comb = mapFloat(ohmsAtual, OHMS_CHEIO, OHMS_MEIO, 100.0, 50.0);
  } else {
    // Mapeia de Meio (175) até Vazio (418) -> 50% a 0%
    pct_comb = mapFloat(ohmsAtual, OHMS_MEIO, OHMS_VAZIO, 50.0, 0.0);
  }

  // Fixa valor entre 0 e 100%
  pct_comb = constrain(pct_comb, 0, 100);

  
}

}// fim do update_combustivel
