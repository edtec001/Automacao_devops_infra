
void update_tempMotor() {
  
// 1. Acumula leituras na velocidade máxima do processador
  somaADC_NTC += analogRead(PINO_NTC);
  contador_NTC++;
  delay(5);
   
  // 2. Processa quando atingir o volume de amostras
  if (contador_NTC >= AMOSTRAS_NTC) {
    float mediaADC_NTC = (float)somaADC_NTC / AMOSTRAS_NTC;

    // Converte leitura para Ohms (Divisor de Tensão)
    if (mediaADC_NTC >= 1023) mediaADC_NTC = 1022; // Segurança contra divisão por zero
    float ohmsAtual = R_FIXO_NTC / ((1023.0 / mediaADC_NTC) - 1.0);

    // Converte Ohms para Temperatura (Interpolação Linear)
    float tempC = calcularTemperatura(ohmsAtual);
    temp_motor=round(tempC);
    
    // Reseta acumuladores
    somaADC_NTC = 0;
    contador_NTC = 0;
    
    }
}    
