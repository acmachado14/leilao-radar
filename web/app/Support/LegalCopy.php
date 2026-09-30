<?php

namespace App\Support;

class LegalCopy
{
    public const CONTACT_EMAIL = 'contato.verifycar@gmail.com';

    /**
     * @return list<string>
     */
    public static function termsParagraphs(): array
    {
        return [
            'Última atualização: 19 de setembro de 2026.',
            'O VerifyRadar é um serviço de consulta a lotes de leilão, cruzamento com a Tabela FIPE e parecer assistido por IA. O catálogo é informativo. Lance, arrematação e regularização do veículo são de responsabilidade do usuário junto ao leiloeiro.',
            'O parecer gerado por IA é uma estimativa automatizada e pode conter erros ou informações imprecisas (“alucinações”). O VerifyRadar não se responsabiliza por lances, arrematações ou qualquer decisão tomada com base nesse parecer. Confira sempre os dados oficiais do leiloeiro antes de agir.',
            'No site, a assinatura pode ser contratada com um atendente. No aplicativo iOS, assinaturas digitais (análises de IA e recortes de alerta) são vendidas exclusivamente pela App Store, via compra no aplicativo.',
            'As assinaturas iOS são mensais e renovam automaticamente, a menos que sejam canceladas pelo menos 24 horas antes do fim do período vigente. O valor é cobrado no Apple ID na confirmação da compra e novamente em até 24 horas antes de cada renovação. O usuário gerencia ou cancela em Ajustes → Apple ID → Assinaturas. Há oferta introdutória de 7 dias grátis na App Store; depois vale o preço mensal do plano.',
            'Você pode excluir a conta no aplicativo (Conta → Apagar conta) ou pedindo ao suporte. O uso da IA está sujeito à cota do plano vigente. A conta grátis (sem assinatura) inclui o catálogo e 3 análises de IA.',
            'Contato: '.self::CONTACT_EMAIL.'.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function privacyParagraphs(): array
    {
        return [
            'Última atualização: 19 de setembro de 2026.',
            'Tratamos nome, e-mail, telefone opcional, recortes de alerta, lotes marcados com interesse e histórico de análises de IA para operar o serviço.',
            'No iOS, a Apple e a RevenueCat processam a compra da assinatura. Identificadores de conta e de compra são usados para liberar o plano. Não enviamos cartão nem PIX pelo aplicativo.',
            'Você pode pedir acesso, correção ou exclusão dos dados pelo próprio app ou pelo e-mail '.self::CONTACT_EMAIL.'.',
            'Controlador: VerifyCar / VerifyRadar. Base legal: execução de contrato e legítimo interesse na segurança da conta.',
        ];
    }
}
