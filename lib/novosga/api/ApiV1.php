<?php
namespace novosga\api;

use \novosga\business\AtendimentoBusiness;

/**
 * Api V1
 *
 * @author rogeriolino
 */
class ApiV1 extends Api {
    
    /**
     * Retorna todas as prioridades disponíveis
     * @return array
     */
    public function prioridades() {
        return $this->em->createQuery('
            SELECT 
                e.id, e.nome
            FROM
                novosga\model\Prioridade e
            WHERE 
                e.status = 1
            ORDER BY 
                e.nome ASC
        ')->getResult();
    }
    
    /**
     * Retorna todos os locais de atendimento
     * @return array
     */
    public function locais() {
        return $this->em->createQuery('
            SELECT 
                e.id, e.nome
            FROM
                novosga\model\Local e
            ORDER BY 
                e.nome ASC
        ')->getResult();
    }
    
    /**
     * Retorna todas as unidades ativas
     * @return array
     */
    public function unidades() {
        return $this->em->createQuery('
            SELECT 
                e.id, e.codigo, e.nome
            FROM
                novosga\model\Unidade e
            WHERE
                e.status = 1
            ORDER BY 
                e.nome ASC
        ')->getResult();
    }
    
    /**
     * Retorna os serviços globais ou os serviços disponíveis na unidade informada
     * @param int $unidade
     * @return array
     */
    public function servicos() {
        if (func_num_args() === 0) {
            throw new \Exception(_('Unidade não informada'));
        }
        $unidade = func_get_arg(0);
        if ($unidade == 0) {
            // servicos globais
            return $this->em->createQuery('
                SELECT 
                    e.id, e.nome
                FROM
                    novosga\model\Servico e
                ORDER BY 
                    e.nome ASC
            ')->getResult();
        } else {
            // servicos da unidade
            return $this->em->createQuery('
                SELECT 
                    s.id, e.sigla, e.nome, l.nome as local
                FROM
                    novosga\model\ServicoUnidade e
                    JOIN e.servico s
                    JOIN e.local l
                WHERE
                    e.status = 1 AND
                    e.unidade = :unidade
                ORDER BY 
                    e.nome ASC
            ')->setParameter(':unidade', $unidade)
                ->getResult();
        }
    }
    
    /**
     * Retorna a fila de atendimento global da unidade. Especificando o serviço ou não
     * @param int $unidade
     * @param int $servico
     * @return array
     */
    public function atendimentos() {
        if (func_num_args() === 0) {
            throw new \Exception(_('Unidade não informada'));
        }
        $unidade = func_get_arg(0);
        $servico = (func_num_args() > 1) ? func_get_arg(1) : 0;
        // servicos da unidade
        return $this->em->createQuery('
            SELECT 
                e.id, su.sigla, su.nome as servico, e.numeroSenha,
                e.dataChegada, e.dataInicio, e.dataFim, e.status
            FROM
                novosga\model\Atendimento e
                JOIN e.servicoUnidade su
                JOIN su.servico s
                JOIN e.prioridadeSenha p
            WHERE
                su.unidade = :unidade AND
                (
                    s.id = :servico OR
                    :servico = 0
                )
            ORDER BY 
                p.peso DESC,
                e.numeroSenha ASC
        ')->setParameter(':unidade', $unidade)
            ->setParameter(':servico', $servico)
            ->getResult();
    }
    
    /**
     * Retorna as senhas para serem exibidas no painel
     * @param int $unidade
     * @param string $servicos | 1,2,3,4...
     * @return array
     */
    public function painel() {
        if (func_num_args() === 0) {
            throw new \Exception(_('Unidade não informada'));
        }
        $unidade = func_get_arg(0);
        $servicos = \novosga\util\Arrays::value($_GET, 'servicos', '');
        if (empty($servicos)) {
            $servicos = 0;
        }
        $length = \novosga\model\util\Senha::LENGTH;
        // servicos da unidade
        return $this->em->createQuery("
            SELECT 
                e.id, e.siglaSenha as sigla, e.mensagem, e.numeroSenha as numero, 
                e.local, e.numeroLocal as numeroLocal, e.peso,
                $length as length
            FROM
                novosga\model\PainelSenha e
                JOIN e.servico s
            WHERE
                e.unidade = :unidade AND
                s.id IN (:servicos)
            ORDER BY 
                e.id DESC
        ")->setParameter(':unidade', $unidade)
            ->setParameter(':servicos', explode(',', $servicos))
            ->getResult();
    }
    
    /**
     * Retorna a fila de atendimento do usuário
     * @param int $unidade
     * @param int $usuario
     * @return array
     */
    public function fila() {
        if (func_num_args() === 0) {
            throw new \Exception(_('Unidade não informada'));
        }
        $unidade = func_get_arg(0);
        $usuario = (func_num_args() > 1) ? func_get_arg(1) : 0;
        // servicos que o usuario atende
        $servicos = $this->em->createQuery('
            SELECT 
                s.id
            FROM 
                novosga\model\ServicoUsuario e 
                JOIN e.servico s 
            WHERE 
                e.usuario = :usuario AND 
                e.unidade = :unidade AND 
                s.status = 1
        ')->setParameter('unidade', $unidade)
            ->setParameter('usuario', $usuario)
            ->getResult();
        if (!empty($servicos)) {
            // fila de atendimento
            return $this->em->createQuery("
                SELECT 
                    e.id, su.sigla, su.nome as servico, e.numeroSenha as numero,
                    e.dataChegada, e.dataInicio, e.dataFim, e.status
                FROM 
                    novosga\model\Atendimento e 
                    JOIN e.prioridadeSenha p
                    JOIN e.servicoUnidade su 
                    JOIN su.servico s 
                WHERE 
                    e.status = :status AND
                    su.unidade = :unidade AND
                    s.id IN (:servicos)
                ORDER BY 
                    p.peso DESC,
                    e.numeroSenha ASC
            ")->setParameter('servicos', $servicos)
                ->setParameter('unidade', $unidade)
                ->setParameter('status', AtendimentoBusiness::SENHA_EMITIDA)
                ->getResult();
        }
        return array();
    }
    
}